<?php
class OfferModel
{
    private PDO $pdo;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    /*список публикаций*/
    public function getActive(string $searchQuery = '', int $categoryId = 0, string $date = ''): array
    {
        $sql = 'SELECT offers.*, users.name AS author_name, categories.name AS category_name 
                FROM offers 
                JOIN users ON offers.user_id = users.id 
                JOIN categories ON offers.category_id = categories.id 
                WHERE offers.status = "Активно" ';
        $params = [];
        if ($searchQuery !== '') {
            $sql .= 'AND (offers.title LIKE ? OR offers.description LIKE ?) ';
            $params[] = '%' . $searchQuery . '%';
            $params[] = '%' . $searchQuery . '%';
        }
        if ($categoryId > 0) {
            $sql .= 'AND offers.category_id = ? ';
            $params[] = $categoryId;
        }
        if ($date !== '') {
            $sql .= 'AND DATE(offers.created_at) = ? ';
            $params[] = $date;
        }
        $sql .= 'ORDER BY offers.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    /*список категорий*/
    public function getCategories(): array
    {
        return $this->pdo->query('SELECT * FROM categories')->fetchAll();
    }
    /*ID публикаций на которые откликались*/
    public function getUserRespondedOfferIds(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT offer_id FROM responses WHERE sender_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}