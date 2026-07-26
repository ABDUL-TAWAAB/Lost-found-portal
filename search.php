<?php
// =================================================================
// Search and Browse Page (search.php)
// Allows visitors and registered users to search and filter items
// based on keywords, category, item type, location, and status.
// =================================================================

// 1. Include dependencies
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 2. Fetch all categories from database to populate the filter dropdown
$cat_query = "SELECT * FROM categories ORDER BY category_name ASC";
$cat_result = mysqli_query($conn, $cat_query);

// 3. Initialize search filters from $_GET
$keyword = isset($_GET['keyword']) ? sanitize_input($_GET['keyword']) : "";
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$type = isset($_GET['type']) ? sanitize_input($_GET['type']) : "all";
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : "Open"; // Defaults to showing Open items
$location = isset($_GET['location']) ? sanitize_input($_GET['location']) : "";

// 4. Construct SQL dynamically based on selected filters
// We start with a base query that fetches items joined with categories
$sql = "SELECT i.*, c.category_name 
        FROM items i 
        JOIN categories c ON i.category_id = c.id 
        WHERE 1 = 1"; // "1=1" is a standard trick to easily append "AND" clauses

$params = [];
$param_types = "";

// Filter by keyword (searches title or description)
if (!empty($keyword)) {
    $sql .= " AND (i.title LIKE ? OR i.description LIKE ?)";
    $keyword_param = "%" . $keyword . "%";
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $param_types .= "ss";
}

// Filter by category
if ($category_id > 0) {
    $sql .= " AND i.category_id = ?";
    $params[] = $category_id;
    $param_types .= "i";
}

// Filter by type (lost / found)
if ($type !== "all") {
    $sql .= " AND i.item_type = ?";
    $params[] = $type;
    $param_types .= "s";
}

// Filter by status (Open / claimed / recovered / archived)
if ($status !== "all") {
    $sql .= " AND i.status = ?";
    $params[] = $status;
    $param_types .= "s";
}

// Filter by location
if (!empty($location)) {
    $sql .= " AND i.location LIKE ?";
    $location_param = "%" . $location . "%";
    $params[] = $location_param;
    $param_types .= "s";
}

// Order by date reported (most recent first)
$sql .= " ORDER BY i.created_at DESC";

// 5. Prepare and execute the dynamic query
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    die("Database query error: " . mysqli_error($conn));
}

$base_path = "";
include_once 'includes/header.php';
?>

<!-- Search Bar / Filters Header Section -->
<section class="search-header-panel">
    <div class="search-title">
        <h2>Browse & Search Items</h2>
        <p>Use the filters below to narrow down your search for lost or found items.</p>
    </div>

    <!-- Search Form -->
    <form action="search.php" method="GET" class="search-filter-form">
        <div class="filter-row">
            <div class="filter-group keyword-search">
                <label for="keyword"><i class="fas fa-search"></i> Keywords</label>
                <input type="text" id="keyword" name="keyword" placeholder="Search title or details..." value="<?php echo htmlspecialchars($keyword); ?>">
            </div>

            <div class="filter-group">
                <label for="category"><i class="fas fa-tags"></i> Category</label>
                <select id="category" name="category">
                    <option value="0">All Categories</option>
                    <?php 
                    // Reset categories pointer and loop
                    mysqli_data_seek($cat_result, 0);
                    while ($cat = mysqli_fetch_assoc($cat_result)): 
                    ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_id === (int)$cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="type"><i class="fas fa-sliders-h"></i> Report Type</label>
                <select id="type" name="type">
                    <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All (Lost & Found)</option>
                    <option value="lost" <?php echo $type === 'lost' ? 'selected' : ''; ?>>Lost Items</option>
                    <option value="found" <?php echo $type === 'found' ? 'selected' : ''; ?>>Found Items</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="status"><i class="fas fa-check-circle"></i> Status</label>
                <select id="status" name="status">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="Open" <?php echo $status === 'Open' ? 'selected' : ''; ?>>Open (Unresolved)</option>
                    <option value="claimed" <?php echo $status === 'claimed' ? 'selected' : ''; ?>>Claimed</option>
                    <option value="recovered" <?php echo $status === 'recovered' ? 'selected' : ''; ?>>Recovered</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                <input type="text" id="location" name="location" placeholder="e.g. Library" value="<?php echo htmlspecialchars($location); ?>">
            </div>
        </div>

        <div class="filter-submit-wrapper">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
            <a href="search.php" class="btn btn-outline"><i class="fas fa-undo"></i> Reset Filters</a>
        </div>
    </form>
</section>

<!-- Search Results Grid Section -->
<section class="results-section">
    <div class="results-info">
        <p><i class="fas fa-info-circle text-blue"></i> Found <strong><?php echo mysqli_num_rows($result); ?></strong> matching reported item(s).</p>
    </div>

    <div class="items-grid">
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($item = mysqli_fetch_assoc($result)): ?>
                <!-- Item Card -->
                <div class="item-card">
                    <div class="card-image-wrapper">
                        <!-- Render item image -->
                        <img src="assets/uploads/<?php echo !empty($item['image']) && $item['image'] !== 'default_item.jpg' ? $item['image'] : 'default_item.jpg'; ?>" alt="<?php echo $item['title']; ?>" class="card-img" onerror="this.src='./assets/uploads/default_item.jpg';">
                        <div class="card-type-tag">
                            <?php echo get_type_badge($item['item_type']); ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card-badges-row">
                            <span class="card-category"><i class="fas fa-tag"></i> <?php echo $item['category_name']; ?></span>
                            <?php echo get_status_badge($item['status']); ?>
                        </div>
                        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <p class="card-desc"><?php echo substr(htmlspecialchars($item['description']), 0, 100) . (strlen($item['description']) > 100 ? '...' : ''); ?></p>
                        
                        <div class="card-meta">
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($item['location']); ?></span>
                            <span><i class="far fa-calendar-alt"></i> <?php echo format_date($item['date_lost_found']); ?></span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="item.php?id=<?php echo $item['id']; ?>" class="btn btn-outline btn-full">View & Contact <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <!-- Empty State if no items found -->
            <div class="empty-state span-all">
                <i class="fas fa-search-minus empty-icon"></i>
                <h3>No Items Found</h3>
                <p>We couldn't find any items matching your exact filter criteria. Try broadening your keywords or resetting the status filters!</p>
                <a href="search.php" class="btn btn-primary mt-1">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);
include_once 'includes/footer.php';
?>
