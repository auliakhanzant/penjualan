<?php
$userRole = $_SESSION['status'] ?? 'kasir';
?>

<?php if ($userRole === 'admin'): ?>
            </div><!-- /container-fluid -->
        </div><!-- /page-content-wrapper -->
    </div><!-- /wrapper -->
<?php else: ?>
    </div><!-- /container -->
<?php endif; ?>

<script src="asset/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<script>
setTimeout(() => {
    document.querySelectorAll('.flash-alert, .alert-dismissible').forEach(el => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 3000);
</script>
</body>
</html>
