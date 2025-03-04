<?php
class TransactionController extends BaseController {
    private $transactionModel;
    private $cryptoAssetModel;
    private $giftCardModel;
    private $walletModel;
    private $notificationService;

    public function __construct() {
        $this->requireAuth();
        
        $this->transactionModel = new Transaction();
        $this->cryptoAssetModel = new CryptoAsset();
        $this->giftCardModel = new GiftCard();
        $this->walletModel = new Wallet();
        $this->notificationService = NotificationService::getInstance();
    }

    public function cryptoForm() {
        $cryptoAssets = $this->cryptoAssetModel->getAll(true);

        return $this->render('transaction/sell_crypto', [
            'pageTitle' => 'Sell Cryptocurrency',
            'cryptoAssets' => $cryptoAssets
        ]);
    }

    public function processCrypto() {
        try {
            $this->validateCSRFToken();

            $userId = $this->getCurrentUser()['id'];
            $data = [
                'crypto_asset_id' => (int)$_POST['crypto_asset_id'],
                'amount' => (float)$_POST['amount']
            ];

            // Validate input
            if (empty($data['crypto_asset_id']) || empty($data['amount'])) {
                throw new Exception('All fields are required');
            }

            // Get crypto asset details
            $cryptoAsset = $this->cryptoAssetModel->getById($data['crypto_asset_id']);
            if (!$cryptoAsset) {
                throw new Exception('Invalid cryptocurrency selected');
            }

            // Validate amount
            $this->cryptoAssetModel->validateAmount($data['crypto_asset_id'], $data['amount']);

            // Generate unique wallet address for the transaction
            $data['wallet_address'] = $this->cryptoAssetModel->generateWalletAddress(
                $data['crypto_asset_id'],
                $userId
            );

            // Set current rate
            $data['rate'] = $cryptoAsset['rate'];

            // Create transaction
            $transactionId = $this->transactionModel->createCryptoTransaction($userId, $data);

            // Send notifications
            $this->notificationService->sendMultiChannelNotification(
                $userId,
                'Crypto Transaction Created',
                "Please send {$data['amount']} {$cryptoAsset['symbol']} to the provided wallet address.",
                'crypto_transaction',
                [
                    'transaction_id' => $transactionId,
                    'amount' => $data['amount'],
                    'symbol' => $cryptoAsset['symbol'],
                    'wallet_address' => $data['wallet_address']
                ]
            );

            $this->setFlashMessage('success', 'Transaction created successfully. Please send the cryptocurrency to the provided wallet address.');
            $this->redirect("/transaction/crypto/{$transactionId}");
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
            $this->redirect('/sell/crypto');
        }
    }

    public function giftCardForm() {
        $giftCards = $this->giftCardModel->getAll(true);

        return $this->render('transaction/sell_giftcard', [
            'pageTitle' => 'Sell Gift Card',
            'giftCards' => $giftCards
        ]);
    }

    public function processGiftCard() {
        try {
            $this->validateCSRFToken();

            $userId = $this->getCurrentUser()['id'];
            $data = [
                'gift_card_id' => (int)$_POST['gift_card_id'],
                'subcategory_id' => (int)$_POST['subcategory_id'],
                'amount' => (float)$_POST['amount'],
                'card_code' => $this->sanitizeInput($_POST['card_code'])
            ];

            // Validate input
            if (empty($data['gift_card_id']) || empty($data['amount']) || empty($data['card_code'])) {
                throw new Exception('All fields are required');
            }

            // Get gift card details
            $giftCard = $this->giftCardModel->getById($data['gift_card_id']);
            if (!$giftCard) {
                throw new Exception('Invalid gift card selected');
            }

            // Validate amount
            $this->giftCardModel->validateAmount($data['gift_card_id'], $data['amount'], $data['subcategory_id']);

            // Handle image uploads
            $data['images'] = [];
            if (isset($_FILES['card_images'])) {
                $data['images'] = $this->handleGiftCardImageUploads($_FILES['card_images']);
            }

            if (empty($data['images'])) {
                throw new Exception('At least one card image is required');
            }

            // Set current rate
            $data['rate'] = $giftCard['rate'];
            if ($data['subcategory_id']) {
                foreach ($giftCard['subcategories'] as $subcategory) {
                    if ($subcategory['id'] == $data['subcategory_id']) {
                        $data['rate'] += $subcategory['rate_adjustment'];
                        break;
                    }
                }
            }

            // Create transaction
            $transactionId = $this->transactionModel->createGiftCardTransaction($userId, $data);

            // Send notifications
            $this->notificationService->sendMultiChannelNotification(
                $userId,
                'Gift Card Transaction Created',
                "Your gift card transaction has been submitted for review.",
                'giftcard_transaction',
                [
                    'transaction_id' => $transactionId,
                    'amount' => $data['amount'],
                    'gift_card' => $giftCard['name']
                ]
            );

            $this->setFlashMessage('success', 'Transaction created successfully. Our team will review your submission.');
            $this->redirect("/transaction/giftcard/{$transactionId}");
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
            $this->redirect('/sell/giftcard');
        }
    }

    private function handleGiftCardImageUploads($files) {
        $uploadedImages = [];
        $errors = [];

        // Ensure files array is properly structured
        if (!isset($files['tmp_name']) || !is_array($files['tmp_name'])) {
            throw new Exception('Invalid file upload structure');
        }

        foreach ($files['tmp_name'] as $index => $tmpName) {
            if ($files['error'][$index] === UPLOAD_ERR_OK) {
                $fileInfo = [
                    'name' => $files['name'][$index],
                    'type' => $files['type'][$index],
                    'tmp_name' => $tmpName,
                    'error' => $files['error'][$index],
                    'size' => $files['size'][$index]
                ];

                // Validate file
                $validationErrors = $this->validateFileUpload(
                    $fileInfo,
                    ['image/jpeg', 'image/png'],
                    5 * 1024 * 1024 // 5MB limit
                );

                if (empty($validationErrors)) {
                    // Generate unique filename
                    $extension = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);
                    $filename = 'giftcard_' . uniqid() . '.' . $extension;
                    $uploadPath = UPLOAD_PATH . '/giftcards/' . $filename;

                    if (move_uploaded_file($tmpName, $uploadPath)) {
                        $uploadedImages[] = $filename;
                    } else {
                        $errors[] = "Failed to upload file: {$fileInfo['name']}";
                    }
                } else {
                    $errors = array_merge($errors, $validationErrors);
                }
            }
        }

        if (!empty($errors)) {
            // Clean up any uploaded files if there were errors
            foreach ($uploadedImages as $filename) {
                @unlink(UPLOAD_PATH . '/giftcards/' . $filename);
            }
            throw new Exception(implode("\n", $errors));
        }

        return $uploadedImages;
    }

    public function viewTransaction($id) {
        $userId = $this->getCurrentUser()['id'];
        $transaction = $this->transactionModel->getById($id);

        if (!$transaction || $transaction['user_id'] !== $userId) {
            $this->setFlashMessage('error', 'Transaction not found');
            $this->redirect('/dashboard');
        }

        $viewData = [
            'pageTitle' => 'Transaction Details',
            'transaction' => $transaction
        ];

        if ($transaction['type'] === 'crypto') {
            $viewData['cryptoAsset'] = $this->cryptoAssetModel->getById($transaction['crypto_asset_id']);
            return $this->render('transaction/crypto_details', $viewData);
        } else {
            $viewData['giftCard'] = $this->giftCardModel->getById($transaction['gift_card_id']);
            return $this->render('transaction/giftcard_details', $viewData);
        }
    }

    public function getSubcategories() {
        try {
            $giftCardId = (int)$_GET['gift_card_id'];
            $subcategories = $this->giftCardModel->getSubcategories($giftCardId);
            return $this->json($subcategories);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancelTransaction($id) {
        try {
            $this->validateCSRFToken();

            $userId = $this->getCurrentUser()['id'];
            $transaction = $this->transactionModel->getById($id);

            if (!$transaction || $transaction['user_id'] !== $userId) {
                throw new Exception('Transaction not found');
            }

            if ($transaction['status'] !== 'pending') {
                throw new Exception('Only pending transactions can be cancelled');
            }

            if ($this->transactionModel->updateStatus($id, 'cancelled')) {
                $this->setFlashMessage('success', 'Transaction cancelled successfully');
            } else {
                throw new Exception('Failed to cancel transaction');
            }
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
        }

        $this->redirect("/transaction/{$id}");
    }
}
