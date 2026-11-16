<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Vzаимопомощь — Каталог актуальных заявок</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #eef5fc; color: #2c3e50; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header {
            background: white; padding: 15px 30px; border-radius: 12px;
            box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15);
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;
        }
        .header h1 { font-size: 22px; color: #2c3e50; font-weight: 600; }
        .btn-theme-toggle {
            background: #f8fbfe; border: 1.5px solid #d0dfef; color: #2c3e50;
            width: 40px; height: 40px; border-radius: 10px; font-size: 18px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
        }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15); }
        .card h2 { font-size: 18px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eef5fc; color: #34495e; }
        .search-panel {
            background: #f8fbfe; border: 1.5px solid #d0dfef; padding: 15px;
            border-radius: 10px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
        }
        .search-input { flex: 2; min-width: 180px; padding: 10px 12px; border: 1.5px solid #d0dfef; border-radius: 8px; font-size: 14px; }
        .search-select, .search-date { flex: 1; min-width: 140px; padding: 10px 12px; border: 1.5px solid #d0dfef; border-radius: 8px; font-size: 14px; background: #fff; }
        .btn-search { background: #3a97f9; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-reset { background: #eef5fc; color: #5a6b7c; text-decoration: none; padding: 10px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; border: 1.5px solid #d0dfef; }
        .offer-item { display: block; border: 1.5px solid #eef5fc; padding: 20px; border-radius: 10px; margin-bottom: 15px; background: #fcfdfe; }
        .offer-meta { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .category-badge { background: #eef5fc; color: #3a97f9; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
        .status-badge.active { background: #e3fcef; color: #27ae60; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 6px; }
        .author-tag { font-size: 13px; color: #7f8c8d; }
        .offer-title { font-size: 17px; color: #2c3e50; margin-bottom: 8px; font-weight: 600; word-break: break-word; }
        .offer-desc { font-size: 14px; color: #5a6b7c; line-height: 1.5; margin-bottom: 10px; word-break: break-word; }
        .no-data { text-align: center; color: #95adc9; font-style: italic; padding: 30px 0; font-size: 14px; }
        .author-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
}        .btn-respond { background: #3a97f9; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; }
        .responded-label { font-size: 13px; color: #27ae60; font-weight: 600; }
        body.dark-theme { background: #121822; color: #e2e8f0; color-scheme: dark; }
        body.dark-theme .header, body.dark-theme .card { background: #1c2432; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35); }
        body.dark-theme .header h1, body.dark-theme .card h2, body.dark-theme .offer-title { color: #f1f5f9; border-bottom-color: #2d3748; }
        body.dark-theme .search-panel, body.dark-theme .offer-item { background: #161d29; border-color: #2d3748; }
        body.dark-theme .offer-desc, body.dark-theme .author-tag { color: #94a3b8; }
        body.dark-theme .search-input, body.dark-theme .search-select, body.dark-theme .search-date { background: #121822; border-color: #2d3748; color: #f1f5f9; }
        body.dark-theme .btn-reset, body.dark-theme .category-badge { background: #253042; border-color: #334155; color: #93c5fd; }
        body.dark-theme .btn-theme-toggle { background: #161d29; border-color: #2d3748; color: #facc15; }
    </style>
</head>
<body>
<div class="container">
    <header class="header">
        <h1>Vzаимопомощь</h1>
        <div style="display: flex; align-items: center; gap: 12px;">
            <button type="button" id="themeToggle" class="btn-theme-toggle" title="Сменить тему">🌙</button>
            <?php if ($currentUserId > 0): ?>
                <a href="dashboard.php?filter=my" class="btn-respond">Мои заявки и чаты</a>
                <a href="profile.php" style="color: #3a97f9; font-weight: 600; text-decoration: none; font-size: 14px;">Профиль</a>
            <?php else: ?>
                <a href="login.php" class="btn-respond">Войти</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="card">
        <h2>Актуальные публикации студентов (<?= count($offersList) ?>)</h2>

        <form action="index.php" method="GET" class="search-panel">
            <input type="text" name="q" class="search-input" placeholder="Поиск по теме или описанию..." value="<?= htmlspecialchars($searchQuery) ?>">
            <select name="category" class="search-select">
                <option value="0">Все предметы</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $searchCategory === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date" class="search-date" value="<?= htmlspecialchars($searchDate) ?>">
            <button type="submit" class="btn-search">🔍 Найти</button>
            <?php if ($searchQuery !== '' || $searchCategory > 0 || $searchDate !== ''): ?>
                <a href="index.php" class="btn-reset">✖ Сбросить</a>
            <?php endif; ?>
        </form>

        <?php if (empty($offersList)): ?>
            <p class="no-data">Актуальных публикаций не найдено</p>
        <?php else: ?>
            <?php foreach ($offersList as $offer): ?>
                <div class="offer-item">
                    <div class="offer-meta">
                        <div>
                            <span class="category-badge"><?= htmlspecialchars($offer['category_name']) ?></span>
                            <span class="status-badge active"><?= htmlspecialchars($offer['status']) ?></span>
                        </div>
                        <span class="author-tag">
                            <?= date('d.m.Y', strtotime($offer['created_at'])) ?> | 
                            Автор: <a href="user.php?id=<?= (int)$offer['user_id'] ?>" style="color: #3a97f9; text-decoration: none; font-weight: 600;">
                                <?= htmlspecialchars($offer['author_name']) ?>
                            </a>
                        </span>
                    </div>

                    <div class="offer-title"><?= htmlspecialchars($offer['title']) ?></div>
                    <div class="offer-desc"><?= nl2br(htmlspecialchars($offer['description'])) ?></div>

                    <div class="author-actions">
                        <?php if ($currentUserId > 0 && (int)$offer['user_id'] === $currentUserId): ?>
                            <span class="author-tag">Это ваша публикация</span>
                        <?php elseif (in_array($offer['id'], $myRespondedOffers)): ?>
                            <span class="responded-label">✔ Вы уже откликнулись</span>
                        <?php elseif ($currentUserId > 0): ?>
                            <a href="dashboard.php?respond_to=<?= (int)$offer['id'] ?>" class="btn-respond">💬 Откликнуться</a>
                        <?php else: ?>
                            <a href="login.php" class="btn-respond">Войдите, чтобы откликнуться</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>
<script>
const themeToggleBtn = document.getElementById('themeToggle');
if (localStorage.getItem('site_theme') === 'dark') {
    document.body.classList.add('dark-theme');
    themeToggleBtn.textContent = '☀️';
}
themeToggleBtn.addEventListener('click', () => {
    document.body.classList.toggle('dark-theme');
    const isDark = document.body.classList.contains('dark-theme');
    themeToggleBtn.textContent = isDark ? '☀️' : '🌙';
    localStorage.setItem('site_theme', isDark ? 'dark' : 'light');
});
</script>
</body>
</html>