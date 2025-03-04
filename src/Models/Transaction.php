<?php
class Transaction {
    private $db;
    private $table = 'transactions';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function createCryptoTransaction($userId, $data) {
        try {
            $this->db->beginTransaction();

            // Insert transaction
            $sql = "INSERT INTO {$this->table} (
                user_id, type, crypto_asset_id, amount, wallet_address,
                rate, status, created_at
            ) VALUES (
                :user_id, 'crypto', :crypto_asset_id, :amount, :wallet_address,
                :rate, 'pending', NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':crypto_asset_id' => $data['crypto_asset_id'],
                ':amount' => $data['amount'],
                ':wallet_address' => $data['wallet_address'],
                ':rate' => $data['rate']
            ]);

            $transactionId = $this->db->lastInsertId();

            // Create notification
            $notificationService = NotificationService::getInstance();
            $notificationService->createInAppNotification(
                $userId,
                "New crypto transaction created. Please send {$data['amount']} to the provided wallet address.",
                'transaction',
                "/transactions/{$transactionId}"
            );

            $this->db->commit();
            return $transactionId;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating crypto transaction: " . $e->getMessage());
            throw new Exception("Failed to create transaction");
        }
    }

    public function createGiftCardTransaction($userId, $data) {
        try {
            $this->db->beginTransaction();

            // Insert transaction
            $sql = "INSERT INTO {$this->table} (
                user_id, type, gift_card_id, amount, card_code,
                rate, status, created_at
            ) VALUES (
                :user_id, 'giftcard', :gift_card_id, :amount, :card_code,
                :rate, 'pending', NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':gift_card_id' => $data['gift_card_id'],
                ':amount' => $data['amount'],
                ':card_code' => $data['card_code'],
                ':rate' => $data['rate']
            ]);

            $transactionId = $this->db->lastInsertId();

            // Handle image uploads
            if (!empty($data['images'])) {
                $this->saveTransactionImages($transactionId, $data['images']);
            }

            // Create notification
            $notificationService = NotificationService::getInstance();
            $notificationService->createInAppNotification(
                $userId,
                "New gift card transaction created. Our team will verify your submission.",
                'transaction',
                "/transactions/{$transactionId}"
            );

            $this->db->commit();
            return $transactionId;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating gift card transaction: " . $e->getMessage());
            throw new Exception("Failed to create transaction");
        }
    }

    private function saveTransactionImages($transactionId, $images) {
        $sql = "INSERT INTO transaction_images (
            transaction_id, image_path, created_at
        ) VALUES (
            :transaction_id, :image_path, NOW()
        )";

        $stmt = $this->db->prepare($sql);

        foreach ($images as $image) {
            $stmt->execute([
                ':transaction_id' => $transactionId,
                ':image_path' => $image
            ]);
        }
    }

    public function updateStatus($transactionId, $status, $adminId = null) {
        try {
            $this->db->beginTransaction();

            // Get transaction details
            $transaction = $this->getById($transactionId);
            if (!$transaction) {
                throw new Exception("Transaction not found");
            }

            // Update transaction status
            $sql = "UPDATE {$this->table} SET 
                    status = :status,
                    admin_id = :admin_id,
                    updated_at = NOW()
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $transactionId,
                ':status' => $status,
                ':admin_id' => $adminId
            ]);

            // If transaction is completed, process payment
            if ($status === 'completed') {
                $this->processCompletedTransaction($transaction);
            }

            // Create notification
            $notificationService = NotificationService::getInstance();
            $message = "Your transaction has been {$status}.";
            if ($status === 'completed') {
                $message .= " Funds have been credited to your wallet.";
            } elseif ($status === 'rejected') {
                $message .= " Please contact support for more information.";
            }

            $notificationService->sendMultiChannelNotification(
                $transaction['user_id'],
                "Transaction {$status}",
                $message,
                'transaction_status',
                ['transaction_id' => $transactionId]
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating transaction status: " . $e->getMessage());
            throw new Exception("Failed to update transaction status");
        }
    }

    private function processCompletedTransaction($transaction) {
        // Calculate amount to credit
        $amountToCredit = $transaction['amount'] * $transaction['rate'];

        // Credit user's wallet
        $wallet = new Wallet();
        $wallet->updateBalance($transaction['user_id'], $amountToCredit, 'credit');

        // Process referral bonus if applicable
        $wallet->processReferralBonus($transaction['user_id'], $amountToCredit);
    }

    public function getById($id) {
        try {
            $sql = "SELECT t.*, 
                    CASE 
                        WHEN t.type = 'crypto' THEN ca.name
                        WHEN t.type = 'giftcard' THEN gc.name
                    END as asset_name,
                    u.name as user_name,
                    a.name as admin_name
                   FROM {$this->table} t
                   LEFT JOIN crypto_assets ca ON t.crypto_asset_id = ca.id
                   LEFT JOIN gift_cards gc ON t.gift_card_id = gc.id
                   LEFT JOIN users u ON t.user_id = u.id
                   LEFT JOIN users a ON t.admin_id = a.id
                   WHERE t.id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            $transaction = $stmt->fetch();
            
            if ($transaction) {
                // Get images if it's a gift card transaction
                if ($transaction['type'] === 'giftcard') {
                    $transaction['images'] = $this->getTransactionImages($id);
                }
            }
            
            return $transaction;
        } catch (PDOException $e) {
            error_log("Error getting transaction: " . $e->getMessage());
            return false;
        }
    }

    private function getTransactionImages($transactionId) {
        try {
            $stmt = $this->db->prepare("
                SELECT image_path 
                FROM transaction_images 
                WHERE transaction_id = :transaction_id
            ");
            $stmt->execute([':transaction_id' => $transactionId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error getting transaction images: " . $e->getMessage());
            return [];
        }
    }

    public function getUserTransactions($userId, $limit = 10, $offset = 0) {
        try {
            $sql = "SELECT t.*, 
                    CASE 
                        WHEN t.type = 'crypto' THEN ca.name
                        WHEN t.type = 'giftcard' THEN gc.name
                    END as asset_name
                   FROM {$this->table} t
                   LEFT JOIN crypto_assets ca ON t.crypto_asset_id = ca.id
                   LEFT JOIN gift_cards gc ON t.gift_card_id = gc.id
                   WHERE t.user_id = :user_id
                   ORDER BY t.created_at DESC
                   LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting user transactions: " . $e->getMessage());
            return [];
        }
    }

    public function getPendingTransactions() {
        try {
            $sql = "SELECT t.*, 
                    CASE 
                        WHEN t.type = 'crypto' THEN ca.name
                        WHEN t.type = 'giftcard' THEN gc.name
                    END as asset_name,
                    u.name as user_name,
                    u.email as user_email
                   FROM {$this->table} t
                   LEFT JOIN crypto_assets ca ON t.crypto_asset_id = ca.id
                   LEFT JOIN gift_cards gc ON t.gift_card_id = gc.id
                   LEFT JOIN users u ON t.user_id = u.id
                   WHERE t.status = 'pending'
                   ORDER BY t.created_at ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting pending transactions: " . $e->getMessage());
            return [];
        }
    }

    public function getTransactionStats($userId = null) {
        try {
            $params = [];
            $userCondition = "";
            
            if ($userId) {
                $userCondition = "WHERE user_id = :user_id";
                $params[':user_id'] = $userId;
            }

            $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_transactions,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_transactions,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_transactions,
                    SUM(CASE WHEN status = 'completed' THEN amount * rate ELSE 0 END) as total_value,
                    COUNT(CASE WHEN type = 'crypto' THEN 1 END) as crypto_transactions,
                    COUNT(CASE WHEN type = 'giftcard' THEN 1 END) as giftcard_transactions
                   FROM {$this->table}
                   {$userCondition}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting transaction stats: " . $e->getMessage());
            return false;
        }
    }
}
