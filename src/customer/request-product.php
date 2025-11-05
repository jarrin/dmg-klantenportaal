<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../controllers/customer/RequestProductController.php';

$auth = new Auth();
$auth->requireCustomer();

$userId = $auth->getCurrentUserId();

$controller = new RequestProductController($userId);

// Handle POST requests
$result = $controller->handlePost();
$success = $result['success'];
$error = $result['error'];

// Get page data
$data = $controller->index();
extract($data);

$pageTitle = 'Product Aanvragen - ' . APP_NAME;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>Product Aanvragen</h1>
        <a href="/customer/products.php" class="btn btn-secondary">Terug naar producten</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="dashboard-section">
            <h2>Nieuwe Aanvraag</h2>
            <form method="POST" action="">
                <div class="form-group full-width">
                    <label for="product_type_id">Product Type *</label>
                    <select id="product_type_id" name="product_type_id" required onchange="loadProductTypeDetails()">
                        <option value="">-- Selecteer een product type --</option>
                        <?php foreach ($productTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>" data-duration="<?php echo $type['default_duration_months']; ?>" data-description="<?php echo htmlspecialchars($type['description'] ?? ''); ?>">
                                <?php echo htmlspecialchars($type['name']); ?>
                                - €<?php echo htmlspecialchars($type['description'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="product-type-info"></small>
                </div>

                <div class="form-group">
                    <label for="requested_name">Product Naam *</label>
                    <input type="text" id="requested_name" name="requested_name"
                        placeholder="Bijv: Basis Webhosting" required>
                    <small class="margin-top-5">Wordt automatisch ingevuld op basis van product type</small>
                </div>

                <div class="form-group">
                    <label for="requested_domain">Domeinnaam (optioneel)</label>
                    <input type="text" id="requested_domain" name="requested_domain"
                        placeholder="Bijv: mijnbedrijf.nl">
                    <small>Laat leeg indien niet van toepassing</small>
                </div>

                <div class="form-group duration-info full-width" id="durationInfo">
                    <strong>Looptijd:</strong> <span id="durationValue"></span> maanden
                </div>

                <div class="form-group full-width">
                    <label for="additional_info">Aanvullende Informatie</label>
                    <textarea id="additional_info" name="additional_info" rows="4"
                        placeholder="Eventuele extra wensen of opmerkingen..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary full-width">Aanvraag Indienen</button>
            </form>
        </div>

        <div class="dashboard-section">
            <h2>Mijn Aanvragen</h2>
            <?php if (empty($requests)): ?>
                <p>U heeft nog geen aanvragen ingediend.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['requested_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['type_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $request['status']; ?>">
                                        <?php
                                        $statusText = [
                                            'pending' => 'In behandeling',
                                            'approved' => 'Goedgekeurd',
                                            'rejected' => 'Afgewezen',
                                            'completed' => 'Voltooid'
                                        ];
                                        echo $statusText[$request['status']] ?? $request['status'];
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('d-m-Y', strtotime($request['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
    let lastSelectedProductType = null;

    function loadProductTypeDetails() {
        const select = document.getElementById('product_type_id');
        const selectedOption = select.options[select.selectedIndex];
        const productTypeId = select.value;
        const requestedNameInput = document.getElementById('requested_name');

        if (!productTypeId) {
            document.getElementById('durationInfo').style.display = 'none';
            document.getElementById('productTypeInfo').style.display = 'none';
            document.getElementById('requested_name').value = '';
            lastSelectedProductType = null;
            return;
        }

        // Get selected option data
        const duration = selectedOption.dataset.duration;
        const name = selectedOption.textContent.split(' - ')[0].trim();

        // Check if the input contains the previous auto-filled value or is empty
        // If so, update it with the new product type name
        const currentValue = requestedNameInput.value.trim();

        // If field is empty or contains the previously auto-filled value, update it
        if (!currentValue || currentValue === lastSelectedProductType) {
            requestedNameInput.value = name;
            lastSelectedProductType = name;
        } else if (productTypeId !== select.dataset.previousId) {
            // Product type changed but user manually edited the name
            // Still update the field but remember the user can override
            lastSelectedProductType = name;
        }

        // Show duration info
        document.getElementById('durationValue').textContent = duration;
        document.getElementById('durationInfo').style.display = 'block';

        // Store current selection
        select.dataset.previousId = productTypeId;
    }
</script>