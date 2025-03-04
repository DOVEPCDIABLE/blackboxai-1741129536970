<?php
class User {
    private $db;
    private $table = 'users';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data) {
        try {
            // Generate referral code
            $data['referral_code'] = $this->generateReferralCode();
            
            // Hash password
            $data['password'] = password_hash($data['password'] . PASSWORD_PEPPER, PASSWORD_BCRYPT);
            
            $sql = "INSERT INTO {$this->table} (
                name, email, password, referral_code, referred_by,
                theme_preference, notification_preferences, created_at
            ) VALUES (
                :name, :email, :password, :referral_code, :referred_by,
                :theme_preference, :notification_preferences, NOW()
            )";

            $stmt = $this->db->prepare($sql);
            
            $stmt->execute([
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':password' => $data['password'],
                ':referral_code' => $data['referral_code'],
                ':referred_by' => $data['referred_by'] ?? null,
                ':theme_preference' => $data['theme_preference'] ?? DEFAULT_THEME,
                ':notification_preferences' => json_encode([
                    'email' => true,
                    'push' => true,
                    'in_app' => true
                ])
            ]);

            $userId = $this->db->lastInsertId();

            // Create user wallet
            $this->createWallet($userId);

            return $userId;
        } catch (PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            throw new Exception("Failed to create user account");
        }
    }

    private function createWallet($userId) {
        $sql = "INSERT INTO wallets (user_id, balance, created_at) VALUES (:user_id, 0, NOW())";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':user_id' => $userId]);
    }

    public function findById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error finding user by ID: " . $e->getMessage());
            return false;
        }
    }

    public function findByEmail($email) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = :email");
            $stmt->execute([':email' => $email]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error finding user by email: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, $data) {
        try {
            $updateFields = [];
            $params = [':id' => $id];

            foreach ($data as $key => $value) {
                if ($key !== 'id' && $key !== 'password') {
                    $updateFields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }

            if (isset($data['password'])) {
                $updateFields[] = "password = :password";
                $params[':password'] = password_hash($data['password'] . PASSWORD_PEPPER, PASSWORD_BCRYPT);
            }

            $sql = "UPDATE {$this->table} SET " . implode(', ', $updateFields) . 
                   ", updated_at = NOW() WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating user: " . $e->getMessage());
            return false;
        }
    }

    public function verifyPassword($password, $hashedPassword) {
        return password_verify($password . PASSWORD_PEPPER, $hashedPassword);
    }

    public function getReferrals($userId) {
        try {
            $sql = "SELECT u.*, 
                    (SELECT COUNT(*) FROM transactions t 
                     WHERE t.user_id = u.id AND t.status = 'completed') as total_transactions,
                    (SELECT COALESCE(SUM(amount), 0) FROM referral_bonuses 
                     WHERE referred_user_id = u.id) as total_bonus
                   FROM {$this->table} u 
                   WHERE u.referred_by = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting referrals: " . $e->getMessage());
            return [];
        }
    }

    public function updateNotificationPreferences($userId, $preferences) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET notification_preferences = :preferences,
                    updated_at = NOW()
                WHERE id = :user_id
            ");

            return $stmt->execute([
                ':user_id' => $userId,
                ':preferences' => json_encode($preferences)
            ]);
        } catch (PDOException $e) {
            error_log("Error updating notification preferences: " . $e->getMessage());
            return false;
        }
    }

    public function updateTheme($userId, $theme) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET theme_preference = :theme,
                    updated_at = NOW()
                WHERE id = :user_id
            ");

            return $stmt->execute([
                ':user_id' => $userId,
                ':theme' => $theme
            ]);
        } catch (PDOException $e) {
            error_log("Error updating theme preference: " . $e->getMessage());
            return false;
        }
    }

    public function setup2FA($userId, $secret) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET twofactor_secret = :secret,
                    twofactor_enabled = true,
                    updated_at = NOW()
                WHERE id = :user_id
            ");

            return $stmt->execute([
                ':user_id' => $userId,
                ':secret' => $secret
            ]);
        } catch (PDOException $e) {
            error_log("Error setting up 2FA: " . $e->getMessage());
            return false;
        }
    }

    public function disable2FA($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET twofactor_secret = NULL,
                    twofactor_enabled = false,
                    updated_at = NOW()
                WHERE id = :user_id
            ");

            return $stmt->execute([':user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Error disabling 2FA: " . $e->getMessage());
            return false;
        }
    }

    public function getWalletBalance($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT balance FROM wallets 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch();
            return $result ? $result['balance'] : 0;
        } catch (PDOException $e) {
            error_log("Error getting wallet balance: " . $e->getMessage());
            return 0;
        }
    }

    private function generateReferralCode() {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
            
            // Check if code already exists
            $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE referral_code = :code");
            $stmt->execute([':code' => $code]);
        } while ($stmt->fetch());
        
        return $code;
    }

    public function validateReferralCode($code) {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM {$this->table} 
                WHERE referral_code = :code
            ");
            $stmt->execute([':code' => $code]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Error validating referral code: " . $e->getMessage());
            return false;
        }
    }

    public function getBankAccounts($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM user_bank_accounts 
                WHERE user_id = :user_id
                ORDER BY created_at DESC
            ");
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting bank accounts: " . $e->getMessage());
            return [];
        }
    }

    public function addBankAccount($userId, $data) {
        try {
            $sql = "INSERT INTO user_bank_accounts (
                user_id, bank_name, account_number, account_name, created_at
            ) VALUES (
                :user_id, :bank_name, :account_number, :account_name, NOW()
            )";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':user_id' => $userId,
                ':bank_name' => $data['bank_name'],
                ':account_number' => $data['account_number'],
                ':account_name' => $data['account_name']
            ]);
        } catch (PDOException $e) {
            error_log("Error adding bank account: " . $e->getMessage());
            return false;
        }
    }

    public function getTransactionHistory($userId, $limit = 10, $offset = 0) {
        try {
            $sql = "SELECT t.*, 
                    CASE 
                        WHEN t.type = 'crypto' THEN c.name
                        WHEN t.type = 'giftcard' THEN g.name
                    END as asset_name
                   FROM transactions t
                   LEFT JOIN crypto_assets c ON t.crypto_asset_id = c.id
                   LEFT JOIN gift_cards g ON t.gift_card_id = g.id
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
            error_log("Error getting transaction history: " . $e->getMessage());
            return [];
        }
    }
}
