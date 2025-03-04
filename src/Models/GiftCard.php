<?php
class GiftCard {
    private $db;
    private $table = 'gift_cards';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($activeOnly = true) {
        try {
            $sql = "SELECT gc.*, 
                    gc_cat.name as category_name,
                    COUNT(DISTINCT gc_sub.id) as subcategory_count
                   FROM {$this->table} gc
                   LEFT JOIN gift_card_categories gc_cat ON gc.category_id = gc_cat.id
                   LEFT JOIN gift_card_subcategories gc_sub ON gc.id = gc_sub.gift_card_id";
            
            if ($activeOnly) {
                $sql .= " WHERE gc.is_active = 1";
            }
            
            $sql .= " GROUP BY gc.id
                      ORDER BY gc_cat.name ASC, gc.name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $giftCards = $stmt->fetchAll();
            
            // Get subcategories for each gift card
            foreach ($giftCards as &$giftCard) {
                $giftCard['subcategories'] = $this->getSubcategories($giftCard['id']);
            }
            
            return $giftCards;
        } catch (PDOException $e) {
            error_log("Error getting gift cards: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id) {
        try {
            $sql = "SELECT gc.*, 
                    gc_cat.name as category_name
                   FROM {$this->table} gc
                   LEFT JOIN gift_card_categories gc_cat ON gc.category_id = gc_cat.id
                   WHERE gc.id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            $giftCard = $stmt->fetch();
            
            if ($giftCard) {
                $giftCard['subcategories'] = $this->getSubcategories($id);
            }
            
            return $giftCard;
        } catch (PDOException $e) {
            error_log("Error getting gift card: " . $e->getMessage());
            return false;
        }
    }

    public function getSubcategories($giftCardId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM gift_card_subcategories 
                WHERE gift_card_id = :gift_card_id
                ORDER BY name ASC
            ");
            $stmt->execute([':gift_card_id' => $giftCardId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting gift card subcategories: " . $e->getMessage());
            return [];
        }
    }

    public function create($data) {
        try {
            $this->db->beginTransaction();

            // Insert gift card
            $sql = "INSERT INTO {$this->table} (
                name, category_id, description, rate,
                min_amount, max_amount, is_active, created_at
            ) VALUES (
                :name, :category_id, :description, :rate,
                :min_amount, :max_amount, :is_active, NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':name' => $data['name'],
                ':category_id' => $data['category_id'],
                ':description' => $data['description'],
                ':rate' => $data['rate'],
                ':min_amount' => $data['min_amount'],
                ':max_amount' => $data['max_amount'],
                ':is_active' => $data['is_active'] ?? true
            ]);

            $giftCardId = $this->db->lastInsertId();

            // Insert subcategories if provided
            if (!empty($data['subcategories'])) {
                $this->addSubcategories($giftCardId, $data['subcategories']);
            }

            $this->db->commit();
            return $giftCardId;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating gift card: " . $e->getMessage());
            throw new Exception("Failed to create gift card");
        }
    }

    public function update($id, $data) {
        try {
            $this->db->beginTransaction();

            $updateFields = [];
            $params = [':id' => $id];

            foreach ($data as $key => $value) {
                if ($key !== 'id' && $key !== 'subcategories') {
                    $updateFields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }

            $sql = "UPDATE {$this->table} 
                   SET " . implode(', ', $updateFields) . ",
                       updated_at = NOW()
                   WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Update subcategories if provided
            if (isset($data['subcategories'])) {
                // Remove existing subcategories
                $this->removeSubcategories($id);
                // Add new subcategories
                $this->addSubcategories($id, $data['subcategories']);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating gift card: " . $e->getMessage());
            throw new Exception("Failed to update gift card");
        }
    }

    private function addSubcategories($giftCardId, $subcategories) {
        $sql = "INSERT INTO gift_card_subcategories (
            gift_card_id, name, rate_adjustment, created_at
        ) VALUES (
            :gift_card_id, :name, :rate_adjustment, NOW()
        )";

        $stmt = $this->db->prepare($sql);

        foreach ($subcategories as $subcategory) {
            $stmt->execute([
                ':gift_card_id' => $giftCardId,
                ':name' => $subcategory['name'],
                ':rate_adjustment' => $subcategory['rate_adjustment'] ?? 0
            ]);
        }
    }

    private function removeSubcategories($giftCardId) {
        $stmt = $this->db->prepare("
            DELETE FROM gift_card_subcategories 
            WHERE gift_card_id = :gift_card_id
        ");
        return $stmt->execute([':gift_card_id' => $giftCardId]);
    }

    public function updateRate($id, $rate, $subcategoryRates = []) {
        try {
            $this->db->beginTransaction();

            // Update main gift card rate
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET rate = :rate,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':rate' => $rate
            ]);

            // Update subcategory rates if provided
            if (!empty($subcategoryRates)) {
                $stmt = $this->db->prepare("
                    UPDATE gift_card_subcategories 
                    SET rate_adjustment = :rate_adjustment,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                foreach ($subcategoryRates as $subcategoryId => $adjustment) {
                    $stmt->execute([
                        ':id' => $subcategoryId,
                        ':rate_adjustment' => $adjustment
                    ]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating gift card rates: " . $e->getMessage());
            throw new Exception("Failed to update gift card rates");
        }
    }

    public function toggleStatus($id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET is_active = NOT is_active,
                    updated_at = NOW()
                WHERE id = :id
            ");

            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("Error toggling gift card status: " . $e->getMessage());
            throw new Exception("Failed to update gift card status");
        }
    }

    public function validateAmount($giftCardId, $amount, $subcategoryId = null) {
        try {
            $giftCard = $this->getById($giftCardId);
            if (!$giftCard) {
                throw new Exception("Invalid gift card");
            }

            if ($amount < $giftCard['min_amount']) {
                throw new Exception("Amount is below minimum limit of {$giftCard['min_amount']}");
            }

            if ($giftCard['max_amount'] > 0 && $amount > $giftCard['max_amount']) {
                throw new Exception("Amount exceeds maximum limit of {$giftCard['max_amount']}");
            }

            // Validate subcategory if provided
            if ($subcategoryId) {
                $found = false;
                foreach ($giftCard['subcategories'] as $subcategory) {
                    if ($subcategory['id'] == $subcategoryId) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new Exception("Invalid subcategory");
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error validating amount: " . $e->getMessage());
            throw $e;
        }
    }

    public function getCategories() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM gift_card_categories 
                ORDER BY name ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting gift card categories: " . $e->getMessage());
            return [];
        }
    }

    public function getTransactionStats($giftCardId = null) {
        try {
            $params = [];
            $whereClause = "";
            
            if ($giftCardId) {
                $whereClause = "WHERE t.gift_card_id = :gift_card_id";
                $params[':gift_card_id'] = $giftCardId;
            }

            $sql = "SELECT 
                    gc.name,
                    gc_cat.name as category,
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END) as total_volume,
                    AVG(t.rate) as average_rate,
                    COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_transactions,
                    COUNT(CASE WHEN t.status = 'pending' THEN 1 END) as pending_transactions
                   FROM transactions t
                   JOIN {$this->table} gc ON t.gift_card_id = gc.id
                   LEFT JOIN gift_card_categories gc_cat ON gc.category_id = gc_cat.id
                   {$whereClause}
                   GROUP BY gc.id, gc.name, gc_cat.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting gift card transaction stats: " . $e->getMessage());
            return [];
        }
    }
}
