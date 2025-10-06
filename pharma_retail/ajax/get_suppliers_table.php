<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';

try {
    $pdo = getModulePDO();

    $company_id = $_SESSION['company_id'] ?? 0;
    $year_id    = $_SESSION['financial_year_id'] ?? 0;

    $sql = "SELECT id, sup_id, supplier_name, contact_number, gstin_no 
            FROM supplier_details 
            WHERE company_id = :company_id 
              AND accounting_year_id = :year_id
            ORDER BY supplier_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':company_id' => $company_id,
        ':year_id'    => $year_id
    ]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<?php if (!empty($suppliers)): ?>
    <!-- 🔍 Search bar -->
    <div class="mb-3">
        <input type="text" class="form-control" id="supplierSearch" placeholder="🔍 Search supplier...">
    </div>

    <!-- Supplier Table -->
    <table class="table table-sm table-bordered table-striped table-hover" id="supplierTable">
        <thead class="table-primary">
            <tr>
                <th>Code</th>
                <th>Supplier Name</th>
                <th>Contact Number</th>
                <th>GSTIN</th>
                <th style="width: 120px;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['sup_id']) ?></td>
                    <td><?= htmlspecialchars($s['supplier_name']) ?></td>
                    <td><?= htmlspecialchars($s['contact_number']) ?></td>
                    <td><?= htmlspecialchars($s['gstin_no']) ?></td>
                    <td>
                        <button type="button" 
                                class="btn btn-sm btn-outline-primary select-supplier" 
                                data-id="<?= $s['id'] ?>" 
                                data-name="<?= htmlspecialchars($s['supplier_name']) ?>">
                            ✅ Select
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- "No results" message (hidden by default) -->
    <div id="noSupplierMsg" class="alert alert-warning d-flex justify-content-between align-items-center" style="display:none;">
        <span>🚫 No supplier found.</span>
        <a href="../add/add_supplier.php" class="btn btn-sm btn-success">➕ Add Supplier</a>
    </div>

<?php else: ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <span>No suppliers found for this company/year. Please add a new one.</span>
        <a href="../add/add_supplier.php" class="btn btn-sm btn-success">➕ Add Supplier</a>
    </div>
<?php endif; ?>


<!-- jQuery script for live search -->
<script>
$(document).ready(function(){
    $("#supplierSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        var visibleRows = 0;

        $("#supplierTable tbody tr").filter(function() {
            var match = $(this).text().toLowerCase().indexOf(value) > -1;
            $(this).toggle(match);
            if (match) visibleRows++;
        });
    }