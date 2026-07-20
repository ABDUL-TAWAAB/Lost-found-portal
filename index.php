<?php
// =================================================================
// Home Page / Landing Page (index.php)
// This is the public entrance to the application. It displays
// introductory information, portal stats, and the most recent reports.
// =================================================================

// 1. Include dependencies
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 2. Define base path for header links
$base_path = "";
include_once 'includes/header.php';

// 3. Fetch statistics counts for display cards
// Count total active lost items
$total_lost = get_count($conn, "SELECT COUNT(*) FROM items WHERE item_type = 'lost' AND status = 'Open'");
// Count total active found items
$total_found = get_count($conn, "SELECT COUNT(*) FROM items WHERE item_type = 'found' AND status = 'Open'");
// Count total recovered items (claimed/recovered)
$total_recovered = get_count($conn, "SELECT COUNT(*) FROM items WHERE status = 'recovered' OR status = 'claimed'");

// 4. Fetch the 4 most recent active items to show in cards
$recent_items_query = "SELECT i.*, c.category_name 
                       FROM items i 
                       JOIN categories c ON i.category_id = c.id 
                       WHERE i.status = 'Open' 
                       ORDER BY i.created_at DESC 
                       LIMIT 4";
$recent_result = mysqli_query($conn, $recent_items_query);
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-content">
        <h1>Did you lose or find something on campus?</h1>
        <p>Welcome to the School Lost & Found Portal. Join our community to report lost belongings, return found items to their owners, and help keep our campus safe and organized!</p>
        <div class="hero-buttons">
            <a href="user/add-item.php?type=lost" class="btn btn-primary btn-large"><i class="fas fa-search"></i> Report Lost Item</a>
            <a href="user/add-item.php?type=found" class="btn btn-success btn-large"><i class="fas fa-hand-holding-heart"></i> Report Found Item</a>
        </div>
    </div>
    <div class="hero-graphic">
        <i class="fas fa-box-open hero-icon-large"></i>
    </div>
</section>

<!-- Stats Grid -->
<section class="stats-container">
    <div class="stat-card">
        <div class="stat-icon bg-light-blue text-blue"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-info">
            <h3><?php echo $total_lost; ?></h3>
            <p>Active Lost Items</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-light-green text-green"><i class="fas fa-clipboard-check"></i></div>
        <div class="stat-info">
            <h3><?php echo $total_found; ?></h3>
            <p>Active Found Items</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-light-gold text-gold"><i class="fas fa-smile"></i></div>
        <div class="stat-info">
            <h3><?php echo $total_recovered; ?></h3>
            <p>Items Reunited</p>
        </div>
    </div>
</section>

<!-- Recent Items Section -->
<section class="recent-section">
    <div class="section-header">
        <h2><i class="far fa-clock text-blue"></i> Recently Reported Items</h2>
        <p>Have you seen any of these? Click on any item to view full details and contact the poster.</p>
    </div>

    <div class="items-grid">
        <?php if (mysqli_num_rows($recent_result) > 0): ?>
            <?php while ($item = mysqli_fetch_assoc($recent_result)): ?>
                <!-- Item Card -->
                <div class="item-card">
                    <div class="card-image-wrapper">
                        <!-- Display uploaded image or placeholder -->
                        <img src="assets/uploads/<?php echo !empty($item['image']) && $item['image'] !== 'default_item.png' ? $item['image'] : 'default_item.png'; ?>" alt="<?php echo $item['title']; ?>" class="card-img" onerror="this.src='https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&q=80&w=400'">
                        <div class="card-type-tag">
                            <?php echo get_type_badge($item['item_type']); ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <span class="card-category"><i class="fas fa-tag"></i> <?php echo $item['category_name']; ?></span>
                        <h3 class="card-title"><?php echo $item['title']; ?></h3>
                        <p class="card-desc"><?php echo substr($item['description'], 0, 100) . (strlen($item['description']) > 100 ? '...' : ''); ?></p>
                        
                        <div class="card-meta">
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo $item['location']; ?></span>
                            <span><i class="far fa-calendar-alt"></i> <?php echo format_date($item['date_lost_found']); ?></span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="item.php?id=<?php echo $item['id']; ?>" class="btn btn-outline btn-full">View Details <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search-minus empty-icon"></i>
                <p>No active lost or found items reported recently. Try browsing the complete directory!</p>
                <a href="search.php" class="btn btn-primary">Browse Directory</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="view-all-center">
        <a href="search.php" class="btn btn-secondary btn-large">View All Reported Items</a>
    </div>
</section>

<!-- About & Help Info -->
<section class="info-section">
    <div class="info-grid">
        <div class="info-card">
            <h3><i class="fas fa-shield-alt text-green"></i> How It Works</h3>
            <ul>
                <li><strong>1. Register:</strong> Create a student/staff account using your university credentials.</li>
                <li><strong>2. Post Report:</strong> Fill out a quick form describing the item, adding a location and uploading a picture.</li>
                <li><strong>3. Connect:</strong> Use our integrated messaging system to secure, verify, and coordinate item return.</li>
            </ul>
        </div>
        <div class="info-card">
            <h3><i class="fas fa-life-ring text-blue"></i> Safety Advice</h3>
            <ul>
                <li>Never meet in a private, dark, or isolated location to exchange items.</li>
                <li>Prefer busy campus areas (e.g., student association offices or library desks).</li>
                <li>For expensive belongings (laptops, phones), ask the recipient to unlock the device or verify custom identifying marks before handing it over.</li>
            </ul>
        </div>
    </div>
</section>

<?php
// 5. Close database connection
mysqli_close($conn);

// 6. Include footer
include_once 'includes/footer.php';
?>
