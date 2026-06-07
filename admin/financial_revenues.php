<?php
require_once __DIR__ . '/_layout.php';

// Auto-migration
$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS daily_settlements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    settlement_date DATE NOT NULL,
    cashier_id INT NOT NULL,
    expected_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    actual_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expected_card DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    actual_card DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expected_wallet DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    actual_wallet DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expected_other DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    actual_other DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cash_diff DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    non_cash_diff DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cashier_date (settlement_date, cashier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Handle AJAX actions
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];
    try {
        if ($action === 'get_cashier_sales') {
            $date = $_GET['date'] ?? date('Y-m-d');
            $cashierId = (int) ($_GET['cashier_id'] ?? 0);

            // Expected sales
            $stmt = $db->prepare("SELECT 
                SUM(CASE WHEN payment_method = 'cash' AND (customer_type IS NULL OR customer_type NOT IN ('staff', 'room')) THEN (total - refund_amount) ELSE 0 END) as expected_cash,
                SUM(CASE WHEN customer_type = 'staff' THEN (total - refund_amount) ELSE 0 END) as expected_card,
                SUM(CASE WHEN payment_method = 'wallet' AND (customer_type IS NULL OR customer_type NOT IN ('staff', 'room')) THEN (total - refund_amount) ELSE 0 END) as expected_wallet,
                SUM(CASE WHEN customer_type = 'room' THEN (total - refund_amount) ELSE 0 END) as expected_other
                FROM orders 
                WHERE status = 'paid' AND cashier_id = ? AND DATE(paid_at) = ?");
            $stmt->execute([$cashierId, $date]);
            $expected = $stmt->fetch(PDO::FETCH_ASSOC);

            // Existing settlement
            $stmt2 = $db->prepare("SELECT * FROM daily_settlements WHERE settlement_date = ? AND cashier_id = ?");
            $stmt2->execute([$date, $cashierId]);
            $existing = $stmt2->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'expected' => [
                    'cash' => (float) ($expected['expected_cash'] ?? 0),
                    'card' => (float) ($expected['expected_card'] ?? 0),
                    'wallet' => (float) ($expected['expected_wallet'] ?? 0),
                    'other' => (float) ($expected['expected_other'] ?? 0),
                ],
                'existing' => $existing ?: null
            ]);
            exit;
        }

        if ($action === 'save_settlement') {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $date = $data['date'] ?? date('Y-m-d');
            $cashierId = (int) ($data['cashier_id'] ?? 0);
            $actualCash = (float) ($data['actual_cash'] ?? 0);
            $actualCard = (float) ($data['actual_card'] ?? 0);
            $actualWallet = (float) ($data['actual_wallet'] ?? 0);
            $actualOther = (float) ($data['actual_other'] ?? 0);
            $notes = trim($data['notes'] ?? '');

            if (!$cashierId) {
                echo json_encode(['success' => false, 'message' => 'يجب اختيار كاشير']);
                exit;
            }

            // Re-calculate expected from DB to prevent tampering
            $stmt = $db->prepare("SELECT 
                SUM(CASE WHEN payment_method = 'cash' AND (customer_type IS NULL OR customer_type NOT IN ('staff', 'room')) THEN (total - refund_amount) ELSE 0 END) as expected_cash,
                SUM(CASE WHEN customer_type = 'staff' THEN (total - refund_amount) ELSE 0 END) as expected_card,
                SUM(CASE WHEN payment_method = 'wallet' AND (customer_type IS NULL OR customer_type NOT IN ('staff', 'room')) THEN (total - refund_amount) ELSE 0 END) as expected_wallet,
                SUM(CASE WHEN customer_type = 'room' THEN (total - refund_amount) ELSE 0 END) as expected_other
                FROM orders 
                WHERE status = 'paid' AND cashier_id = ? AND DATE(paid_at) = ?");
            $stmt->execute([$cashierId, $date]);
            $expected = $stmt->fetch(PDO::FETCH_ASSOC);

            $expCash = (float) ($expected['expected_cash'] ?? 0);
            $expCard = (float) ($expected['expected_card'] ?? 0);
            $expWallet = (float) ($expected['expected_wallet'] ?? 0);
            $expOther = (float) ($expected['expected_other'] ?? 0);

            $cashDiff = $actualCash - $expCash;
            $nonCashDiff = ($actualCard + $actualWallet + $actualOther) - ($expCard + $expWallet + $expOther);

            $stmtSave = $db->prepare("INSERT INTO daily_settlements 
                (settlement_date, cashier_id, expected_cash, actual_cash, expected_card, actual_card, expected_wallet, actual_wallet, expected_other, actual_other, cash_diff, non_cash_diff, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                expected_cash=VALUES(expected_cash), actual_cash=VALUES(actual_cash),
                expected_card=VALUES(expected_card), actual_card=VALUES(actual_card),
                expected_wallet=VALUES(expected_wallet), actual_wallet=VALUES(actual_wallet),
                expected_other=VALUES(expected_other), actual_other=VALUES(actual_other),
                cash_diff=VALUES(cash_diff), non_cash_diff=VALUES(non_cash_diff),
                notes=VALUES(notes), created_by=VALUES(created_by)");

            $stmtSave->execute([
                $date,
                $cashierId,
                $expCash,
                $actualCash,
                $expCard,
                $actualCard,
                $expWallet,
                $actualWallet,
                $expOther,
                $actualOther,
                $cashDiff,
                $nonCashDiff,
                $notes,
                $user['id']
            ]);

            logActivity("تسوية إيرادات يومية", "التسوية للتاريخ $date للكاشير معرف $cashierId");
            echo json_encode(['success' => true, 'message' => 'تم حفظ وترحيل التسوية اليومية بنجاح']);
            exit;
        }

        if ($action === 'get_history') {
            $from = $_GET['from'] ?? date('Y-m-d');
            $to = $_GET['to'] ?? date('Y-m-d');

            $stmt = $db->prepare("SELECT s.*, u.name as cashier_name, cb.name as creator_name 
                FROM daily_settlements s
                JOIN users u ON s.cashier_id = u.id
                JOIN users cb ON s.created_by = cb.id
                WHERE s.settlement_date BETWEEN ? AND ?
                ORDER BY s.settlement_date DESC, s.id DESC");
            $stmt->execute([$from, $to]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'export_history_excel') {
            $from = $_GET['from'] ?? date('Y-m-d');
            $to = $_GET['to'] ?? date('Y-m-d');

            $stmt = $db->prepare("SELECT s.*, u.name as cashier_name, cb.name as creator_name 
                FROM daily_settlements s
                JOIN users u ON s.cashier_id = u.id
                JOIN users cb ON s.created_by = cb.id
                WHERE s.settlement_date BETWEEN ? AND ?
                ORDER BY s.settlement_date DESC, s.id DESC");
            $stmt->execute([$from, $to]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="Settlements_History_' . $from . '_to_' . $to . '.xls"');
            header('Cache-Control: max-age=0');
            echo "\xEF\xBB\xBF"; // UTF-8 BOM

            echo "<table border='1' dir='rtl'>";
            echo "<tr><th colspan='9' style='background:#2c3e50; color:#fff; font-size:16px; padding:10px;'>سجل تسوية الإيرادات اليومية (من $from إلى $to)</th></tr>";
            echo "<tr style='background:#f4f4f4;'>
                    <th>التاريخ</th>
                    <th>الكاشير</th>
                    <th>الإجمالي المتوقع</th>
                    <th>الإجمالي المستلم</th>
                    <th>فارق الكاش</th>
                    <th>فارق غير النقدي (آجل ومحافظ)</th>
                    <th>المرحل بواسطة</th>
                    <th>تاريخ الترحيل</th>
                    <th>الملاحظات</th>
                  </tr>";

            foreach ($data as $r) {
                $totalExp = (float)$r['expected_cash'] + (float)$r['expected_card'] + (float)$r['expected_wallet'] + (float)$r['expected_other'];
                $totalAct = (float)$r['actual_cash'] + (float)$r['actual_card'] + (float)$r['actual_wallet'] + (float)$r['actual_other'];
                $cashDiff = (float)$r['cash_diff'];
                $nonCashDiff = (float)$r['non_cash_diff'];
                
                $cashDiffTxt = ($cashDiff == 0) ? '0.00' : (($cashDiff > 0) ? '+' . number_format($cashDiff, 2) : number_format($cashDiff, 2));
                $nonCashDiffTxt = ($nonCashDiff == 0) ? '0.00' : (($nonCashDiff > 0) ? '+' . number_format($nonCashDiff, 2) : number_format($nonCashDiff, 2));

                echo "<tr>
                        <td style='text-align:center;'>{$r['settlement_date']}</td>
                        <td>{$r['cashier_name']}</td>
                        <td style='text-align:right;'>$totalExp</td>
                        <td style='text-align:right; font-weight:bold;'>$totalAct</td>
                        <td style='text-align:right;'>$cashDiffTxt</td>
                        <td style='text-align:right;'>$nonCashDiffTxt</td>
                        <td>{$r['creator_name']}</td>
                        <td style='text-align:center;'>{$r['created_at']}</td>
                        <td>{$r['notes']}</td>
                      </tr>";
            }
            echo "</table>";
            exit;
        }

        if ($action === 'export_settlement_excel') {
            $date = $_GET['date'] ?? date('Y-m-d');
            $cashierId = (int)($_GET['cashier_id'] ?? 0);

            if (!$cashierId) {
                die("Error: Cashier ID is required.");
            }

            $uStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
            $uStmt->execute([$cashierId]);
            $cashier = $uStmt->fetch();
            $cashierName = $cashier ? $cashier['name'] : "غير معروف";

            $stmt = $db->prepare("SELECT 
                SUM(CASE WHEN payment_method = 'cash' AND (customer_type IS NULL OR customer_type NOT IN ('staff', 'room')) THEN (total - refund_amount) ELSE 0 END) as expected_cash,
                SUM(CASE WHEN customer_type = 'staff' THEN (total - refund_amount) ELSE 0 END) as expected_card,
                SUM(CASE WHEN payment_method = 'wallet' AND (customer_type IS NULL OR customer_type NOT IN ('staff', 'room')) THEN (total - refund_amount) ELSE 0 END) as expected_wallet,
                SUM(CASE WHEN customer_type = 'room' THEN (total - refund_amount) ELSE 0 END) as expected_other
                FROM orders 
                WHERE status = 'paid' AND cashier_id = ? AND DATE(paid_at) = ?");
            $stmt->execute([$cashierId, $date]);
            $expected = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt2 = $db->prepare("SELECT s.*, cb.name as creator_name FROM daily_settlements s
                LEFT JOIN users cb ON s.created_by = cb.id
                WHERE s.settlement_date = ? AND s.cashier_id = ?");
            $stmt2->execute([$date, $cashierId]);
            $existing = $stmt2->fetch(PDO::FETCH_ASSOC);

            $expCash = (float)($expected['expected_cash'] ?? 0);
            $expCard = (float)($expected['expected_card'] ?? 0);
            $expWallet = (float)($expected['expected_wallet'] ?? 0);
            $expOther = (float)($expected['expected_other'] ?? 0);

            $actCash = $existing ? (float)$existing['actual_cash'] : $expCash;
            $actCard = $existing ? (float)$existing['actual_card'] : $expCard;
            $actWallet = $existing ? (float)$existing['actual_wallet'] : $expWallet;
            $actOther = $existing ? (float)$existing['actual_other'] : $expOther;

            $diffCash = $actCash - $expCash;
            $diffCard = $actCard - $expCard;
            $diffWallet = $actWallet - $expWallet;
            $diffOther = $actOther - $expOther;

            $totalExp = $expCash + $expCard + $expWallet + $expOther;
            $totalAct = $actCash + $actCard + $actWallet + $actOther;
            $totalDiff = $totalAct - $totalExp;

            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="Settlement_' . $date . '_' . str_replace(' ', '_', $cashierName) . '.xls"');
            header('Cache-Control: max-age=0');
            echo "\xEF\xBB\xBF"; // UTF-8 BOM

            echo "<table border='1' dir='rtl'>";
            echo "<tr><th colspan='4' style='background:#2c3e50; color:#fff; font-size:16px; padding:10px;'>مطابقة وتسوية إيرادات اليوم الكاشير: $cashierName (التاريخ: $date)</th></tr>";
            
            if ($existing) {
                echo "<tr><td colspan='4' style='background:#fcf8e3; font-weight:bold;'>حالة التسوية: تم الحفظ والترحيل بواسطة {$existing['creator_name']} (تاريخ الترحيل: {$existing['created_at']})</td></tr>";
            } else {
                echo "<tr><td colspan='4' style='background:#f2dede; font-weight:bold;'>حالة التسوية: مسودة (لم ترحل مسبقاً)</td></tr>";
            }

            echo "<tr style='background:#f4f4f4;'>
                    <th>طريقة الدفع / البند</th>
                    <th>المبلغ المتوقع (من النظام)</th>
                    <th>المبلغ الفعلي المستلم (عداً)</th>
                    <th>الفارق (عجز / زيادة)</th>
                  </tr>";

            $diffCashTxt = ($diffCash == 0) ? '0.00' : (($diffCash > 0) ? '+' . number_format($diffCash, 2) : number_format($diffCash, 2));
            echo "<tr>
                    <td>💸 نقد (Cash)</td>
                    <td style='text-align:right;'>$expCash</td>
                    <td style='text-align:right;'>$actCash</td>
                    <td style='text-align:right; font-weight:bold; color:" . ($diffCash < 0 ? '#ff0000' : ($diffCash > 0 ? '#008000' : '#000')) . "'>$diffCashTxt</td>
                  </tr>";

            $diffWalletTxt = ($diffWallet == 0) ? '0.00' : (($diffWallet > 0) ? '+' . number_format($diffWallet, 2) : number_format($diffWallet, 2));
            echo "<tr>
                    <td>📱 محافظ رقمية (Wallet)</td>
                    <td style='text-align:right;'>$expWallet</td>
                    <td style='text-align:right;'>$actWallet</td>
                    <td style='text-align:right; font-weight:bold; color:" . ($diffWallet < 0 ? '#ff0000' : ($diffWallet > 0 ? '#008000' : '#000')) . "'>$diffWalletTxt</td>
                  </tr>";

            $diffCardTxt = ($diffCard == 0) ? '0.00' : (($diffCard > 0) ? '+' . number_format($diffCard, 2) : number_format($diffCard, 2));
            echo "<tr>
                    <td>👤 مبيعات الموظفين (Staff)</td>
                    <td style='text-align:right;'>$expCard</td>
                    <td style='text-align:right;'>$actCard</td>
                    <td style='text-align:right; font-weight:bold; color:" . ($diffCard < 0 ? '#ff0000' : ($diffCard > 0 ? '#008000' : '#000')) . "'>$diffCardTxt</td>
                  </tr>";

            $diffOtherTxt = ($diffOther == 0) ? '0.00' : (($diffOther > 0) ? '+' . number_format($diffOther, 2) : number_format($diffOther, 2));
            echo "<tr>
                    <td>🔑 مبيعات الغرف (Rooms)</td>
                    <td style='text-align:right;'>$expOther</td>
                    <td style='text-align:right;'>$actOther</td>
                    <td style='text-align:right; font-weight:bold; color:" . ($diffOther < 0 ? '#ff0000' : ($diffOther > 0 ? '#008000' : '#000')) . "'>$diffOtherTxt</td>
                  </tr>";

            $totalDiffTxt = ($totalDiff == 0) ? '0.00' : (($totalDiff > 0) ? '+' . number_format($totalDiff, 2) : number_format($totalDiff, 2));
            echo "<tr style='background:#f4f4f4; font-weight:bold;'>
                    <td>الإجمالي الكلي</td>
                    <td style='text-align:right;'>$totalExp</td>
                    <td style='text-align:right;'>$totalAct</td>
                    <td style='text-align:right; color:" . ($totalDiff < 0 ? '#ff0000' : ($totalDiff > 0 ? '#008000' : '#000')) . "'>$totalDiffTxt</td>
                  </tr>";

            if ($existing && !empty($existing['notes'])) {
                echo "<tr><td colspan='4'><b>الملاحظات:</b> " . htmlspecialchars($existing['notes']) . "</td></tr>";
            }

            echo "</table>";
            exit;
        }

        if ($action === 'delete_settlement') {
            $id = (int) ($_GET['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM daily_settlements WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم حذف التسوية']);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ في النظام: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch all active cashiers
$cashiers = $db->query("SELECT u.id, u.name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'cashier' OR u.role_id = 3 ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);

adminHeader('الإيرادات اليومية', 'financial_revenues');
?>

<style>
    .financial-container {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 20px;
        align-items: start;
    }

    @media (max-width: 992px) {
        .financial-container {
            grid-template-columns: 1fr;
        }
    }

    .diff-badge {
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: bold;
        display: inline-block;
    }

    .diff-zero {
        background-color: var(--bg);
        color: var(--text);
        border: 1px solid var(--border);
    }

    .diff-positive {
        background-color: #d1fae5;
        color: #065f46;
        border: 1px solid #10b981;
    }

    .diff-negative {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #ef4444;
    }

    .finance-table input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-weight: bold;
        font-family: monospace;
        font-size: 1rem;
        background: var(--bg-card);
        color: var(--text);
    }

    .finance-table td {
        padding: 12px 8px;
    }

    .expected-lbl {
        font-family: monospace;
        font-weight: 700;
        font-size: 1.05rem;
    }
</style>

<div class="financial-container">

    <!-- Left Column: Settlement Entry Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-invoice-dollar text-primary"></i> تسوية ومطابقة الإيراد اليومي</h3>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px">
                <div class="form-group">
                    <label class="form-label">تاريخ التسوية *</label>
                    <input type="date" id="settlement-date" class="form-control" value="<?= date('Y-m-d') ?>"
                        onchange="loadExpectedSales()">
                </div>
                <div class="form-group">
                    <label class="form-label">الكاشير *</label>
                    <select id="settlement-cashier" class="form-control" onchange="loadExpectedSales()">
                        <option value="">-- اختر الكاشير --</option>
                        <?php foreach ($cashiers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Reconciliation Work Area -->
            <div id="reconciliation-area" style="display:none">
                <h4
                    style="margin-bottom:12px; border-bottom:2px solid var(--border); padding-bottom:8px; color:var(--primary)">
                    <i class="fas fa-balance-scale"></i> تفاصيل المطابقة والتدقيق</h4>

                <table class="finance-table" style="width:100%; border-collapse:collapse; margin-bottom:20px">
                    <thead>
                        <tr style="background:var(--bg); border-bottom:2px solid var(--border)">
                            <th style="padding:10px; text-align:right">طريقة الدفع</th>
                            <th style="padding:10px; text-align:right">المبلغ المتوقع (من النظام)</th>
                            <th style="padding:10px; text-align:right">المبلغ الفعلي المستلم (عداً)</th>
                            <th style="padding:10px; text-align:center">الفارق (عجز / زيادة)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Cash -->
                        <tr style="border-bottom:1px solid var(--border)">
                            <td><strong>💸 نقد (Cash)</strong></td>
                            <td class="expected-lbl" id="exp-cash">0.00</td>
                            <td><input type="number" id="act-cash" step="0.01" value="0.00"
                                    oninput="calculateDifferences()"></td>
                            <td style="text-align:center"><span id="diff-cash-badge"
                                    class="diff-badge diff-zero">0.00</span></td>
                        </tr>
                        <!-- Digital Wallets -->
                        <tr style="border-bottom:1px solid var(--border)">
                            <td><strong>📱 محافظ رقمية (Wallet)</strong></td>
                            <td class="expected-lbl" id="exp-wallet">0.00</td>
                            <td><input type="number" id="act-wallet" step="0.01" value="0.00"
                                    oninput="calculateDifferences()"></td>
                            <td style="text-align:center"><span id="diff-wallet-badge"
                                    class="diff-badge diff-zero">0.00</span></td>
                        </tr>
                        <!-- Staff Sales (mapped to Card) -->
                        <tr style="border-bottom:1px solid var(--border)">
                            <td><strong>👤 مبيعات الموظفين (Staff)</strong></td>
                            <td class="expected-lbl" id="exp-card">0.00</td>
                            <td><input type="number" id="act-card" step="0.01" value="0.00"
                                    oninput="calculateDifferences()"></td>
                            <td style="text-align:center"><span id="diff-card-badge"
                                    class="diff-badge diff-zero">0.00</span></td>
                        </tr>
                        <!-- Room Sales (mapped to Other) -->
                        <tr style="border-bottom:2px solid var(--border)">
                            <td><strong>🔑 مبيعات الغرف (Rooms)</strong></td>
                            <td class="expected-lbl" id="exp-other">0.00</td>
                            <td><input type="number" id="act-other" step="0.01" value="0.00"
                                    oninput="calculateDifferences()"></td>
                            <td style="text-align:center"><span id="diff-other-badge"
                                    class="diff-badge diff-zero">0.00</span></td>
                        </tr>
                        <!-- Totals Row -->
                        <tr style="background:var(--bg); font-weight:bold">
                            <td style="padding:12px 10px">الإجمالي الكلي</td>
                            <td style="padding:12px 10px; font-family:monospace; font-size:1.1rem" id="total-expected">
                                0.00</td>
                            <td style="padding:12px 10px; font-family:monospace; font-size:1.1rem" id="total-actual">
                                0.00</td>
                            <td style="text-align:center; padding:12px 10px"><span id="total-diff-badge"
                                    class="diff-badge diff-zero">0.00</span></td>
                        </tr>
                    </tbody>
                </table>

                <div class="form-group">
                    <label class="form-label">ملاحظات العجز / الزيادة</label>
                    <textarea id="settlement-notes" class="form-control" rows="3"
                        placeholder="اكتب أي ملاحظات تخص التسوية أو مبررات الفارق المالي هنا..."></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px">
                    <button class="btn btn-success" onclick="exportCurrentSettlementExcel()"><i class="fas fa-file-excel"></i> تصدير التسوية الحالية</button>
                    <button class="btn btn-primary" onclick="postSettlement()"><i class="fas fa-check-circle"></i> ترحيل وتسوية الحساب</button>
                </div>
            </div>

            <!-- Empty Selection State -->
            <div id="empty-state-selection" style="text-align:center; padding:40px; color:var(--text-muted)">
                <i class="fas fa-cash-register" style="font-size:3.5rem; margin-bottom:15px; opacity:.4"></i>
                <p>يرجى اختيار تاريخ الجرد واسم الكاشير لعرض الحسابات وتفاصيل التسوية.</p>
            </div>
        </div>
    </div>

    <!-- Right Column: History Filters and Shifts summary -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history text-info"></i> تصفية سجل التسويات</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">من تاريخ</label>
                <input type="date" id="history-from" class="form-control"
                    value="<?= date('Y-m-d', strtotime('-7 days')) ?>" onchange="loadHistory()">
            </div>
            <div class="form-group">
                <label class="form-label">إلى تاريخ</label>
                <input type="date" id="history-to" class="form-control" value="<?= date('Y-m-d') ?>"
                    onchange="loadHistory()">
            </div>
            <button class="btn btn-secondary btn-block" onclick="loadHistory()"><i class="fas fa-sync"></i> تحديث السجل
                التاريخي</button>
        </div>
    </div>

</div>

<!-- Settlement History Table -->
<div class="card" style="margin-top:20px">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px">
        <h3><i class="fas fa-list-alt text-success"></i> سجل الواردات اليومية المسواة والمرحلة</h3>
        <button class="btn btn-success btn-sm" onclick="exportHistoryExcel()"><i class="fas fa-file-excel"></i> تصدير السجل إلى Excel</button>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table style="width:100%; border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="white-space:nowrap">التاريخ</th>
                        <th style="white-space:nowrap">الكاشير</th>
                        <th style="white-space:nowrap">الإجمالي المتوقع</th>
                        <th style="white-space:nowrap">الإجمالي المستلم</th>
                        <th style="white-space:nowrap">فارق الكاش</th>
                        <th style="white-space:nowrap">فارق غير النقدي (آجل ومحافظ)</th>
                        <th style="white-space:nowrap">المرحل بواسطة</th>
                        <th style="white-space:nowrap">الملاحظات</th>
                        <th style="width:100px; white-space:nowrap">إجراءات</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                    <tr>
                        <td colspan="9" style="text-align:center; padding:20px">لا توجد تسويات مسجلة</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let expectedCash = 0, expectedCard = 0, expectedWallet = 0, expectedOther = 0;

    function exportHistoryExcel() {
        const from = document.getElementById('history-from').value;
        const to = document.getElementById('history-to').value;
        window.location.href = `/admin/financial_revenues.php?action=export_history_excel&from=${from}&to=${to}`;
    }

    function exportCurrentSettlementExcel() {
        const date = document.getElementById('settlement-date').value;
        const cashierId = document.getElementById('settlement-cashier').value;
        if (!date || !cashierId) {
            showToast('يرجى اختيار التاريخ والكاشير أولاً للتصدير.', 'danger');
            return;
        }
        window.location.href = `/admin/financial_revenues.php?action=export_settlement_excel&date=${date}&cashier_id=${cashierId}`;
    }

    async function loadExpectedSales() {
        const date = document.getElementById('settlement-date').value;
        const cashierId = document.getElementById('settlement-cashier').value;

        if (!date || !cashierId) {
            document.getElementById('reconciliation-area').style.display = 'none';
            document.getElementById('empty-state-selection').style.display = 'block';
            return;
        }

        document.getElementById('empty-state-selection').style.display = 'none';

        const res = await apiCall(`/admin/financial_revenues.php?action=get_cashier_sales&date=${date}&cashier_id=${cashierId}`);
        if (!res.success) {
            showToast(res.message, 'danger');
            return;
        }

        // Set expected values
        expectedCash = res.expected.cash;
        expectedCard = res.expected.card;
        expectedWallet = res.expected.wallet;
        expectedOther = res.expected.other;

        document.getElementById('exp-cash').textContent = expectedCash.toFixed(2);
        document.getElementById('exp-card').textContent = expectedCard.toFixed(2);
        document.getElementById('exp-wallet').textContent = expectedWallet.toFixed(2);
        document.getElementById('exp-other').textContent = expectedOther.toFixed(2);

        document.getElementById('total-expected').textContent = (expectedCash + expectedCard + expectedWallet + expectedOther).toFixed(2);

        // Check if there is an existing settlement
        if (res.existing) {
            document.getElementById('act-cash').value = res.existing.actual_cash;
            document.getElementById('act-card').value = res.existing.actual_card;
            document.getElementById('act-wallet').value = res.existing.actual_wallet;
            document.getElementById('act-other').value = res.existing.actual_other;
            document.getElementById('settlement-notes').value = res.existing.notes || '';
            showToast('تنبيه: تم جرد وتسوية هذا الكاشير لهذا اليوم مسبقاً. تعديل المبالغ وتأكيدها سيقوم بتحديث التسوية الحالية.', 'warning');
        } else {
            // Defaults to expected values to help user review quickly
            document.getElementById('act-cash').value = expectedCash.toFixed(2);
            document.getElementById('act-card').value = expectedCard.toFixed(2);
            document.getElementById('act-wallet').value = expectedWallet.toFixed(2);
            document.getElementById('act-other').value = expectedOther.toFixed(2);
            document.getElementById('settlement-notes').value = '';
        }

        document.getElementById('reconciliation-area').style.display = 'block';
        calculateDifferences();
    }

    function calculateDifferences() {
        const actCash = parseFloat(document.getElementById('act-cash').value) || 0;
        const actCard = parseFloat(document.getElementById('act-card').value) || 0;
        const actWallet = parseFloat(document.getElementById('act-wallet').value) || 0;
        const actOther = parseFloat(document.getElementById('act-other').value) || 0;

        const diffCash = actCash - expectedCash;
        const diffCard = actCard - expectedCard;
        const diffWallet = actWallet - expectedWallet;
        const diffOther = actOther - expectedOther;

        updateDiffBadge('diff-cash-badge', diffCash);
        updateDiffBadge('diff-card-badge', diffCard);
        updateDiffBadge('diff-wallet-badge', diffWallet);
        updateDiffBadge('diff-other-badge', diffOther);

        const totalExp = expectedCash + expectedCard + expectedWallet + expectedOther;
        const totalAct = actCash + actCard + actWallet + actOther;
        const totalDiff = totalAct - totalExp;

        document.getElementById('total-actual').textContent = totalAct.toFixed(2);
        updateDiffBadge('total-diff-badge', totalDiff);
    }

    function updateDiffBadge(elementId, value) {
        const badge = document.getElementById(elementId);
        badge.textContent = (value >= 0 ? '+' : '') + value.toFixed(2);

        badge.className = 'diff-badge';
        if (Math.abs(value) < 0.01) {
            badge.classList.add('diff-zero');
        } else if (value > 0) {
            badge.classList.add('diff-positive');
        } else {
            badge.classList.add('diff-negative');
        }
    }

    async function postSettlement() {
        const date = document.getElementById('settlement-date').value;
        const cashierId = document.getElementById('settlement-cashier').value;
        const actCash = parseFloat(document.getElementById('act-cash').value) || 0;
        const actCard = parseFloat(document.getElementById('act-card').value) || 0;
        const actWallet = parseFloat(document.getElementById('act-wallet').value) || 0;
        const actOther = parseFloat(document.getElementById('act-other').value) || 0;
        const notes = document.getElementById('settlement-notes').value;

        if (!confirmAction('هل أنت متأكد من ترحيل وحفظ جرد الإيرادات لهذا اليوم؟ سيتم إثبات العجز أو الزيادة محاسبياً.')) return;

        const res = await apiCall('/admin/financial_revenues.php?action=save_settlement', 'POST', {
            date, cashier_id: cashierId,
            actual_cash: actCash,
            actual_card: actCard,
            actual_wallet: actWallet,
            actual_other: actOther,
            notes
        });

        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
            loadExpectedSales();
            loadHistory();
        }
    }

    async function loadHistory() {
        const from = document.getElementById('history-from').value;
        const to = document.getElementById('history-to').value;

        const res = await apiCall(`/admin/financial_revenues.php?action=get_history&from=${from}&to=${to}`);
        if (!res.success) return;

        const tbody = document.getElementById('history-tbody');
        if (res.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:20px">لا توجد تسويات مسجلة في هذا النطاق</td></tr>';
            return;
        }

        tbody.innerHTML = res.data.map(r => {
            const totalExp = parseFloat(r.expected_cash) + parseFloat(r.expected_card) + parseFloat(r.expected_wallet) + parseFloat(r.expected_other);
            const totalAct = parseFloat(r.actual_cash) + parseFloat(r.actual_card) + parseFloat(r.actual_wallet) + parseFloat(r.actual_other);
            const cashDiff = parseFloat(r.cash_diff);
            const nonCashDiff = parseFloat(r.non_cash_diff);

            return `
        <tr>
            <td style="white-space:nowrap"><strong>${r.settlement_date}</strong></td>
            <td style="white-space:nowrap">${r.cashier_name}</td>
            <td style="font-family:monospace; white-space:nowrap">${totalExp.toFixed(2)}</td>
            <td style="font-family:monospace; font-weight:bold; white-space:nowrap">${totalAct.toFixed(2)}</td>
            <td style="font-family:monospace; white-space:nowrap">${getDiffSpan(cashDiff)}</td>
            <td style="font-family:monospace; white-space:nowrap">${getDiffSpan(nonCashDiff)}</td>
            <td style="white-space:nowrap"><small>${r.creator_name}</small></td>
            <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis" title="${r.notes || ''}">${r.notes || '-'}</td>
            <td style="white-space:nowrap">
                <button class="btn btn-warning btn-sm" onclick="editSettlement('${r.settlement_date}', ${r.cashier_id})" title="تعديل"><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger btn-sm" onclick="deleteSettlement(${r.id})" title="حذف"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
        `;
        }).join('');
    }

    function getDiffSpan(val) {
        if (Math.abs(val) < 0.01) return `<span style="color:#666">0.00</span>`;
        if (val > 0) return `<span style="color:#059669; font-weight:bold">+${val.toFixed(2)} (زيادة)</span>`;
        return `<span style="color:#dc2626; font-weight:bold">${val.toFixed(2)} (عجز)</span>`;
    }

    function editSettlement(date, cashierId) {
        document.getElementById('settlement-date').value = date;
        document.getElementById('settlement-cashier').value = cashierId;
        loadExpectedSales();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function deleteSettlement(id) {
        if (!confirmAction('هل أنت متأكد من حذف هذا السجل محاسبياً؟')) return;
        const res = await apiCall(`/admin/financial_revenues.php?action=delete_settlement&id=${id}`);
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
            loadHistory();
            // If we are currently editing the deleted settlement, reload expected
            const date = document.getElementById('settlement-date').value;
            const cashierId = document.getElementById('settlement-cashier').value;
            if (date && cashierId) {
                loadExpectedSales();
            }
        }
    }

    document.addEventListener('DOMContentLoaded', loadHistory);
</script>

<?php adminFooter(); ?>