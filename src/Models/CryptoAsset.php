<?php
class CryptoAsset {
    private $db;
    private $table = 'crypto_assets';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($activeOnly = true) {
        try {
            $sql = "SELECT * FROM {$this->table}";
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting crypto assets: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->table} 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting crypto asset: " . $e->getMessage());
            return false;
        }
    }

    public function create($data) {
        try {
            $sql = "INSERT INTO {$this->table} (
                name, symbol, rate, min_amount, max_amount,
                wallet_address, is_active, created_at
            ) VALUES (
                :name, :symbol, :rate, :min_amount, :max_amount,
                :wallet_address, :is_active, NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':name' => $data['name'],
                ':symbol' => $data['symbol'],
                ':rate' => $data['rate'],
                ':min_amount' => $data['min_amount'],
                ':max_amount' => $data['max_amount'],
                ':wallet_address' => $data['wallet_address'],
                ':is_active' => $data['is_active'] ?? true
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating crypto asset: " . $e->getMessage());
            throw new Exception("Failed to create crypto asset");
        }
    }

    public function update($id, $data) {
        try {
            $updateFields = [];
            $params = [':id' => $id];

            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $updateFields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }

            $sql = "UPDATE {$this->table} 
                   SET " . implode(', ', $updateFields) . ",
                       updated_at = NOW()
                   WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating crypto asset: " . $e->getMessage());
            throw new Exception("Failed to update crypto asset");
        }
    }

    public function updateRate($id, $rate) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET rate = :rate,
                    updated_at = NOW()
                WHERE id = :id
            ");

            return $stmt->execute([
                ':id' => $id,
                ':rate' => $rate
            ]);
        } catch (PDOException $e) {
            error_log("Error updating crypto rate: " . $e->getMessage());
            throw new Exception("Failed to update crypto rate");
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
            error_log("Error toggling crypto asset status: " . $e->getMessage());
            throw new Exception("Failed to update crypto asset status");
        }
    }

    public function generateWalletAddress($cryptoId, $userId) {
        try {
            // Get crypto asset details
            $crypto = $this->getById($cryptoId);
            if (!$crypto) {
                throw new Exception("Invalid crypto asset");
            }

            // Check if user already has a wallet address for this crypto
            $stmt = $this->db->prepare("
                SELECT wallet_address 
                FROM user_crypto_wallets 
                WHERE user_id = :user_id 
                AND crypto_asset_id = :crypto_id
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':crypto_id' => $cryptoId
            ]);
            $existing = $stmt->fetch();

            if ($existing) {
                return $existing['wallet_address'];
            }

            // Generate new wallet address (this is a placeholder - implement actual wallet generation)
            $walletAddress = $this->generateUniqueWalletAddress($crypto['symbol']);

            // Store the wallet address
            $stmt = $this->db->prepare("
                INSERT INTO user_crypto_wallets (
                    user_id, crypto_asset_id, wallet_address, created_at
                ) VALUES (
                    :user_id, :crypto_id, :wallet_address, NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':crypto_id' => $cryptoId,
                ':wallet_address' => $walletAddress
            ]);

            return $walletAddress;
        } catch (Exception $e) {
            error_log("Error generating wallet address: " . $e->getMessage());
            throw new Exception("Failed to generate wallet address");
        }
    }

    private function generateUniqueWalletAddress($symbol) {
        // This is a placeholder - implement actual wallet address generation
        // In a real application, you would integrate with the appropriate blockchain API
        $prefix = strtolower($symbol);
        $random = bin2hex(random_bytes(20));
        return $prefix . '1' . $random;
    }

    public function validateAmount($cryptoId, $amount) {
        try {
            $crypto = $this->getById($cryptoId);
            if (!$crypto) {
                throw new Exception("Invalid crypto asset");
            }

            if ($amount < $crypto['min_amount']) {
                throw new Exception("Amount is below minimum limit of {$crypto['min_amount']}");
            }

            if ($crypto['max_amount'] > 0 && $amount > $crypto['max_amount']) {
                throw new Exception("Amount exceeds maximum limit of {$crypto['max_amount']}");
            }

            return true;
        } catch (Exception $e) {
            error_log("Error validating amount: " . $e->getMessage());
            throw $e;
        }
    }

    public function getTransactionStats($cryptoId = null) {
        try {
            $params = [];
            $whereClause = "";
            
            if ($cryptoId) {
                $whereClause = "WHERE t.crypto_asset_id = :crypto_id";
                $params[':crypto_id'] = $cryptoId;
            }

            $sql = "SELECT 
                    ca.name,
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END) as total_volume,
                    AVG(t.rate) as average_rate,
                    COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_transactions,
                    COUNT(CASE WHEN t.status = 'pending' THEN 1 END) as pending_transactions
                   FROM transactions t
                   JOIN {$this->table} ca ON t.crypto_asset_id = ca.id
                   {$whereClause}
                   GROUP BY ca.id, ca.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting crypto transaction stats: " . $e->getMessage());
            return [];
        }
    }

    public function getRateHistory($cryptoId, $days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT rate, created_at 
                FROM crypto_rate_history 
                WHERE crypto_asset_id = :crypto_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY created_at ASC
            ");

            $stmt->execute([
                ':crypto_id' => $cryptoId,
                ':days' => $days
            ]);

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting rate history: " . $e->getMessage());
            return [];
        }
    }
}
