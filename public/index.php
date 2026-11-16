<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/OfferModel.php';
require_once __DIR__ . '/../app/Controllers/OfferController.php';
$pdo = getDatabaseConnection();
$offerModel = new OfferModel($pdo);
$offerController = new OfferController($offerModel);
$offerController->index();