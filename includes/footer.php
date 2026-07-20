<?php
// =================================================================
// Footer File (footer.php)
// This file closes the page containers and renders the footer layout,
// links, and script inclusions.
// =================================================================

// Ensure base_path exists
if (!isset($base_path)) {
    $base_path = "";
}
?>
    </main> <!-- End of .page-container -->

    <!-- Footer Layout -->
    <footer class="main-footer">
        <div class="footer-grid">
            <div class="footer-about">
                <h4>School Lost & Found</h4>
                <p>An intuitive online service developed to help university students and staff find lost personal belongings and return found objects in a secure, quick, and efficient manner.</p>
            </div>
            <div class="footer-links">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="<?php echo $base_path; ?>index.php">Home</a></li>
                    <li><a href="<?php echo $base_path; ?>search.php">Browse Items</a></li>
                    <li><a href="<?php echo $base_path; ?>login.php">User Login</a></li>
                    <li><a href="<?php echo $base_path; ?>register.php">Create Account</a></li>
                </ul>
            </div>
            <div class="footer-contact">
                <h4>Support & Help</h4>
                <p><i class="fas fa-map-marker-alt"></i> Campus Security Office, Block C</p>
                <p><i class="fas fa-envelope"></i> lostfound@school.edu</p>
                <p><i class="fas fa-phone-alt"></i> +23353-1691-093 Developer</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> School Lost & Found Portal. Built for Academic Project Defense.</p>
        </div>
    </footer>

    <!-- Custom Client-Side JS -->
    <script src="<?php echo $base_path; ?>assets/js/main.js"></script>
</body>
</html>
