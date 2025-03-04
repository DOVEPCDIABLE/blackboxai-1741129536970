<?php
class Wallet {
    private $db;
    private $table = 'wallets';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getBalance($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT balance 
                FROM {$this->table} 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch();
            return $result ? floatval($result['balance']) : 0.0;
        } catch (PDOException $e) {
            error_log("Error getting wallet balance: " . $e->getMessage());
            throw new Exception("Failed to retrieve wallet balance");
        }
    }

    public function updateBalance($userId, $amount, $type = 'credit') {
        try {
            $this->db->beginTransaction();

            // Get current balance
            $currentBalance = $this->getBalance($userId);
            
            // Calculate new balance
            $newBalance = $type === 'credit' ? 
                         $currentBalance + $amount : 
                         $currentBalance - $amount;

            // Prevent negative balance
            if ($newBalance < 0) {
                throw new Exception("Insufficient funds");
            }

            // Update wallet balance
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET balance = :balance,
                    updated_at = NOW()
                WHERE user_id = :user_id
            ");

            $stmt->execute([
                ':balance' => $newBalance,
                ':user_id' => $userId
            ]);

            // Record transaction
            $this->recordTransaction($userId, $amount, $type);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating wallet balance: " . $e->getMessage());
            throw $e;
        }
    }

    private function recordTransaction($userId, $amount, $type) {
        try {
            $sql = "INSERT INTO wallet_transactions (
                user_id, amount, type, balance_after, created_at
            ) VALUES (
                :user_id, :amount, :type, 
                (SELECT balance FROM {$this->table} WHERE user_id = :user_id),
                NOW()
            )";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':user_id' => $userId,
                ':amount' => $amount,
                ':type' => $type
            ]);
        } catch (PDOException $e) {
            error_log("Error recording wallet transaction: " . $e->getMessage());
            throw new Exception("Failed to record transaction");
        }
    }

    public function processWithdrawal($userId, $amount, $bankAccountId) {
        try {
            $this->db->beginTransaction();

            // Verify sufficient balance
            $currentBalance = $this->getBalance($userId);
            if ($currentBalance < $amount) {
                throw new Exception("Insufficient funds for withdrawal");
            }

            // Get bank account details
            $bankAccount = $this->getBankAccount($bankAccountId, $userId);
            if (!$bankAccount) {
                throw new Exception("Invalid bank account");
            }

            // Create withdrawal request
            $sql = "INSERT INTO withdrawal_requests (
                user_id, amount, bank_account_id, status, created_at
            ) VALUES (
                :user_id, :amount, :bank_account_id, 'pending', NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':amount' => $amount,
                ':bank_account_id' => $bankAccountId
            ]);

            // Deduct amount from wallet
            $this->updateBalance($userId, $amount, 'debit');

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error processing withdrawal: " . $e->getMessage());
            throw $e;
        }
    }

    private function getBankAccount($bankAccountId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM user_bank_accounts 
                WHERE id = :id AND user_id = :user_id
            ");
            $stmt->execute([
                ':id' => $bankAccountId,
                ':user_id' => $userId
            ]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting bank account: " . $e->getMessage());
            return false;
        }
    }

    public function getTransactionHistory($userId, $limit = 10, $offset = 0) {
        try {
            $sql = "SELECT wt.*, 
                    CASE 
                        WHEN wr.id IS NOT NULL THEN 'Withdrawal'
                        WHEN t.id IS NOT NULL THEN 
                            CASE 
                                WHEN t.type = 'crypto' THEN CONCAT('Crypto Sale - ', ca.name)
                                WHEN t.type = 'giftcard' THEN CONCAT('Gift Card Sale - ', gc.name)
                            END
                        ELSE 'System Transaction'
                    END as transaction_type
                   FROM wallet_transactions wt
                   LEFT JOIN withdrawal_requests wr ON wt.reference_id = wr.id AND wt.type = 'withdrawal'
                   LEFT JOIN transactions t ON wt.reference_id = t.id AND wt.type IN ('crypto_sale', 'giftcard_sale')
                   LEFT JOIN crypto_assets ca ON t.crypto_asset_id = ca.id
                   LEFT JOIN gift_cards gc ON t.gift_card_id = gc.id
                   WHERE wt.user_id = :user_id
                   ORDER BY wt.created_at DESC
                   LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting transaction history: " . $e->getMessage());
            return [];
        }
    }

    public function getPendingWithdrawals($userId) {
        try {
            $sql = "SELECT wr.*, uba.bank_name, uba.account_number 
                   FROM withdrawal_requests wr
                   JOIN user_bank_accounts uba ON wr.bank_account_id = uba.id
                   WHERE wr.user_id = :user_id AND wr.status = 'pending'
                   ORDER BY wr.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting pending withdrawals: " . $e->getMessage());
            return [];
        }
    }

    public function cancelWithdrawal($withdrawalId, $userId) {
        try {
            $this->db->beginTransaction();

            // Get withdrawal details
            $stmt = $this->db->prepare("
                SELECT * FROM withdrawal_requests 
                WHERE id = :id AND user_id = :user_id AND status = 'pending'
            ");
            $stmt->execute([
                ':id' => $withdrawalId,
                ':user_id' => $userId
            ]);
            $withdrawal = $stmt->fetch();

            if (!$withdrawal) {
                throw new Exception("Invalid withdrawal request");
            }

            // Update withdrawal status
            $stmt = $this->db->prepare("
                UPDATE withdrawal_requests 
                SET status = 'cancelled',
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $withdrawalId]);

            // Refund amount to wallet
            $this->updateBalance($userId, $withdrawal['amount'], 'credit');

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error cancelling withdrawal: " . $e->getMessage());
            throw $e;
        }
    }

    public function calculateReferralBonus($transactionAmount) {
        return $transactionAmount * (REFERRAL_BONUS_PERCENTAGE / 100);
    }

    public function processReferralBonus($userId, $transactionAmount) {
        try {
            // Get user's referrer
            $stmt = $this->db->prepare("
                SELECT referred_by 
                FROM users 
                WHERE id = :user_id AND referred_by IS NOT NULL
            ");
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch();

            if (!$result) {
                return false; // User has no referrer
            }

            $referrerId = $result['referred_by'];
            $bonusAmount = $this->calculateReferralBonus($transactionAmount);

            // Credit bonus to referrer's wallet
            $this->updateBalance($referrerId, $bonusAmount, 'credit');

            // Record referral bonus
            $sql = "INSERT INTO referral_bonuses (
                referrer_id, referred_user_id, amount, created_at
            ) VALUES (
                :referrer_id, :referred_user_id, :amount, NOW()
            )";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':referrer_id' => $referrerId,
                ':referred_user_id' => $userId,
                ':amount' => $bonusAmount
            ]);
        } catch (Exception $e) {
            error_log("Error processing referral bonus: " . $e->getMessage());
            return false;
        }
    }
}
