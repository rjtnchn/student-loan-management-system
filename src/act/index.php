<?php
/**
 * EcoVolt Utility Billing System
 * This script calculates the final bill for each customer based on their electricity consumption (kWh) 
 * and applies applicable discounts for senior citizens and loyalty energy-saver customers.
 */

// 1. FUNCTIONS

/**
 * Calculates the base bill using a tiered pricing structure
 * @param float $kwh
 * @return float
 */
function calculateBaseBill($kwh) {
    $baseAmount = 0;

    // Tier 1: First 100 kWh @ $0.12
    if ($kwh <= 100) {
        $baseAmount = $kwh * 0.12;
    } 
    // Tier 2: Next 200 kWh (101 to 300) @ $0.15
    elseif ($kwh <= 300) {
        $baseAmount = (100 * 0.12) + (($kwh - 100) * 0.15);
    } 
    // Tier 3: Above 300 kWh @ $0.20
    else {
        $baseAmount = (100 * 0.12) + (200 * 0.15) + (($kwh - 300) * 0.20);
    }

    return $baseAmount;
}

/**
 * Applies senior and loyalty discounts if applicable
 * @param float $baseAmount
 * @param bool $isSenior
 * @return float
 */
function applyDiscounts($baseAmount, $isSenior) {
    $discountRate = 0;

    // Check for senior citizen discount (10%)
    if ($isSenior) {
        $discountRate += 0.10;
    }

    // Check for loyalty energy-saver discount (5% if bill > $100)
    if ($baseAmount > 100) {
        $discountRate += 0.05;
    }

    // Calculate final amount after subtracting total discounts
    $finalAmount = $baseAmount * (1 - $discountRate);
    return $finalAmount;
}

// 2. VARIABLES & ARRAYS (Sample Data)
$customers = [
    [
        "id" => 101,
        "name" => "Alice Smith",
        "consumed_kwh" => 85,
        "is_senior" => false
    ],
    [
        "id" => 102,
        "name" => "Bob Jones",
        "consumed_kwh" => 250,
        "is_senior" => true
    ],
    [
        "id" => 103,
        "name" => "Charlie Brown",
        "consumed_kwh" => 400,
        "is_senior" => false
    ],
    [
        "id" => 104,
        "name" => "Diana Prince",
        "consumed_kwh" => 520,
        "is_senior" => true
    ]
];

$totalRevenue = 0; // Accumulator variable
$reportRows = [];

// 3. LOOP STRUCTURE & OUTPUT GENERATION
foreach ($customers as $customer) {
    // Call the functions to process data
    $baseBill = calculateBaseBill($customer['consumed_kwh']);
    $finalBill = applyDiscounts($baseBill, $customer['is_senior']);

    // Add to total revenue
    $totalRevenue += $finalBill;

    // Format boolean for clean display
    $seniorStatus = $customer['is_senior'] ? "Yes" : "No";

    $reportRows[] = [
        'id' => $customer['id'],
        'name' => $customer['name'],
        'consumed_kwh' => $customer['consumed_kwh'],
        'senior_status' => $seniorStatus,
        'final_bill' => $finalBill,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoVolt Utility Billing Report</title>
    <!-- Custom CSS -->
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --panel: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #dbe3ee;
            --header: #1f4e79;
            --accent: #eaf2ff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(180deg, #eef4ff 0%, var(--bg) 100%);
            color: var(--text);
            padding: 32px 16px;
        }

        .report {
            max-width: 920px;
            margin: 0 auto;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(31, 41, 55, 0.08);
            overflow: hidden;
        }

        .report__header {
            padding: 28px 32px 18px;
            background: linear-gradient(135deg, var(--header), #275f91);
            color: #fff;
        }

        .report__header h1 {
            margin: 0 0 8px;
            font-size: 1.75rem;
        }

        .report__header p {
            margin: 0;
            color: rgba(255, 255, 255, 0.85);
        }

        .report__body {
            padding: 28px 32px 32px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th,
        td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--accent);
            color: var(--header);
            font-size: 0.95rem;
        }

        tbody tr:hover {
            background: #f8fbff;
        }

        td.numeric {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .summary {
            display: flex;
            justify-content: flex-end;
            margin-top: 18px;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .summary span {
            color: var(--header);
            margin-left: 8px;
        }

        @media (max-width: 640px) {
            body {
                padding: 12px;
            }

            .report__header,
            .report__body {
                padding-left: 16px;
                padding-right: 16px;
            }

            table,
            thead,
            tbody,
            th,
            td,
            tr {
                display: block;
            }

            thead {
                position: absolute;
                left: -9999px;
                top: -9999px;
            }

            tbody tr {
                border: 1px solid var(--border);
                border-radius: 12px;
                margin-bottom: 14px;
                overflow: hidden;
            }

            td {
                display: flex;
                justify-content: space-between;
                gap: 16px;
                border-bottom: 1px solid var(--border);
            }

            td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--muted);
            }

            td.numeric {
                text-align: left;
            }

            td:last-child {
                border-bottom: 0;
            }

            .summary {
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <main class="report">
        <header class="report__header">
            <h1>EcoVolt Utility Billing Report</h1>
            <p>Customer usage and final billing summary</p>
        </header>

        <section class="report__body">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>kWh Used</th>
                        <th>Senior?</th>
                        <th>Final Bill</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Report Rows
                        @foreach ($reportRows as $row)
                        It will generate a table row for each customer in the reportRows array, 
                        displaying their ID, Name, kWh Used, Senior status, and Final Bill.
                     -->
                    <?php foreach ($reportRows as $row): ?>
                        <tr>
                            <td data-label="ID"><?php echo htmlspecialchars((string) $row['id']); ?></td>
                            <td data-label="Name"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td class="numeric" data-label="kWh Used"><?php echo htmlspecialchars((string) $row['consumed_kwh']); ?></td>
                            <td data-label="Senior?"><?php echo htmlspecialchars($row['senior_status']); ?></td>
                            <td class="numeric" data-label="Final Bill"><?php echo '$' . number_format($row['final_bill'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="summary">
                Total Revenue Collected <span><?php echo '$' . number_format($totalRevenue, 2); ?></span>
            </div>
        </section>
    </main>
</body>
</html>