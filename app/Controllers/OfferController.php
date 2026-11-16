<?php
class OfferController
{
    private OfferModel $offerModel;
    public function __construct(OfferModel $offerModel)
    {
        $this->offerModel = $offerModel;
    }
    public function index(): void
    {
        $currentUserId = $_SESSION['user_id'] ?? 0;
        //параметры фильтров
        $searchQuery    = isset($_GET['q']) ? trim($_GET['q']) : '';
        $searchCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;
        $searchDate     = isset($_GET['date']) ? trim($_GET['date']) : '';
        //запрос данных
        $offersList        = $this->offerModel->getActive($searchQuery, $searchCategory, $searchDate);
        $categories        = $this->offerModel->getCategories();
        $myRespondedOffers = $currentUserId > 0 
            ? $this->offerModel->getUserRespondedOfferIds($currentUserId) 
            : [];
        //передаем переменные
        require __DIR__ . '/../Views/offers/index.php';
    }
}