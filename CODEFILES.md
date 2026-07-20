JOHNPAUL 
CREATE FOLDER: mysql-init

THEN ASS FILE:loan_payment_schema.sql  

COPY THIS:
-- ─────────────────────────────────────────────────────────
-- Migration: adds loans and payments tables
-- Run this AFTER schema.sql (students table must already exist)
-- Owner: Database Administrator (Johnpaul)
-- ─────────────────────────────────────────────────────────

USE school_db;

-- A student can have many loans (1:M relationship)
CREATE TABLE loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    loan_type ENUM('Tuition', 'Books', 'Living Expenses') NOT NULL,
    status ENUM('Pending', 'Approved', 'Disbursed') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key: every loan must belong to a real student.
    -- ON DELETE CASCADE means if a student is deleted, their loans are deleted too
    -- (prevents "orphan" loans pointing at a student that no longer exists).
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- A loan can have many payments (1:M relationship)
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('Cash', 'Bank Transfer', 'Online Payment') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);

AARON
loanapi.php

<?php
/**
 * Loans API — manages loan records for students.
 *
 * GET    ?student_id=X   → all loans for that student
 * POST                   → create a new loan { student_id, amount, loan_type, status }
 * PUT                    → update a loan's status/amount/type { id, amount, loan_type, status }
 * DELETE                 → remove a loan { id }
 *
 * Same connection details and structure as api.php, so it plugs into the same Docker setup.
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

$host = "db";
$user = "root";
$pass = "rootpassword";
$dbname = "school_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_with_code(["error" => "Connection failed: " . $e->getMessage()], 500);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // READ: get all loans belonging to one student
        $studentId = $_GET['student_id'] ?? null;
        if (!$studentId) {
            echo json_with_code(["error" => "student_id is required"], 400);
            break;
        }
        $stmt = $pdo->prepare("SELECT * FROM loans WHERE student_id = ? ORDER BY id DESC");
        $stmt->execute([$studentId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        // CREATE: add a new loan for a student
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['student_id']) && !empty($data['amount']) && !empty($data['loan_type']) && !empty($data['status'])) {
            $stmt = $pdo->prepare("INSERT INTO loans (student_id, amount, loan_type, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['student_id'],
                $data['amount'],
                $data['loan_type'],
                $data['status']
            ]);
            echo json_encode(["message" => "Loan added successfully!", "id" => $pdo->lastInsertId()]);
        } else {
            echo json_with_code(["error" => "student_id, amount, loan_type, and status are required"], 400);
        }
        break;

    case 'PUT':
        // UPDATE: change a loan's amount, type, or status (e.g. Pending → Approved → Disbursed)
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['id']) && !empty($data['amount']) && !empty($data['loan_type']) && !empty($data['status'])) {
            $stmt = $pdo->prepare("UPDATE loans SET amount = ?, loan_type = ?, status = ? WHERE id = ?");
            $stmt->execute([
                $data['amount'],
                $data['loan_type'],
                $data['status'],
                $data['id']
            ]);
            echo json_encode(["message" => "Loan updated successfully!"]);
        } else {
            echo json_with_code(["error" => "Invalid data provided"], 400);
        }
        break;

    case 'DELETE':
        // DELETE: remove a loan (its payments are removed automatically via ON DELETE CASCADE)
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("DELETE FROM loans WHERE id = ?");
            $stmt->execute([$data['id']]);
            echo json_encode(["message" => "Loan deleted successfully!"]);
        } else {
            echo json_with_code(["error" => "ID required"], 400);
        }
        break;

    default:
        echo json_with_code(["error" => "Method not allowed"], 405);
        break;
}

function json_with_code($data, $code) {
    http_response_code($code);
    return json_encode($data);
}

paymentapi.php

<?php
/**
 * Payments API — manages payment records for a loan, and computes running totals.
 *
 * GET    ?loan_id=X   → { loan_amount, total_paid, remaining_balance, payments: [...] }
 * POST                → create a new payment { loan_id, amount, payment_date, payment_method }
 * DELETE              → remove a payment { id }
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

$host = "db";
$user = "root";
$pass = "rootpassword";
$dbname = "school_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_with_code(["error" => "Connection failed: " . $e->getMessage()], 500);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // READ: get all payments for one loan, plus Total Paid and Remaining Balance
        $loanId = $_GET['loan_id'] ?? null;
        if (!$loanId) {
            echo json_with_code(["error" => "loan_id is required"], 400);
            break;
        }

        // First, get the loan's original amount (needed to compute remaining balance)
        $loanStmt = $pdo->prepare("SELECT amount FROM loans WHERE id = ?");
        $loanStmt->execute([$loanId]);
        $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);

        if (!$loan) {
            echo json_with_code(["error" => "Loan not found"], 404);
            break;
        }

        // Then get every payment made toward this loan
        $paymentsStmt = $pdo->prepare("SELECT * FROM payments WHERE loan_id = ? ORDER BY payment_date DESC");
        $paymentsStmt->execute([$loanId]);
        $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Sum up all payment amounts to get the total paid so far
        $totalPaid = 0;
        foreach ($payments as $payment) {
            $totalPaid += $payment['amount'];
        }

        $loanAmount = $loan['amount'];
        $remainingBalance = $loanAmount - $totalPaid;

        echo json_encode([
            "loan_amount" => $loanAmount,
            "total_paid" => $totalPaid,
            "remaining_balance" => $remainingBalance,
            "payments" => $payments
        ]);
        break;

    case 'POST':
        // CREATE: record a new payment toward a loan
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['loan_id']) && !empty($data['amount']) && !empty($data['payment_date']) && !empty($data['payment_method'])) {
            $stmt = $pdo->prepare("INSERT INTO payments (loan_id, amount, payment_date, payment_method) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['loan_id'],
                $data['amount'],
                $data['payment_date'],
                $data['payment_method']
            ]);
            echo json_encode(["message" => "Payment recorded successfully!"]);
        } else {
            echo json_with_code(["error" => "loan_id, amount, payment_date, and payment_method are required"], 400);
        }
        break;

    case 'DELETE':
        // DELETE: remove a payment record
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->execute([$data['id']]);
            echo json_encode(["message" => "Payment deleted successfully!"]);
        } else {
            echo json_with_code(["error" => "ID required"], 400);
        }
        break;

    default:
        echo json_with_code(["error" => "Method not allowed"], 405);
        break;
}

function json_with_code($data, $code) {
    http_response_code($code);
    return json_encode($data);
}

JOSHUA
index.html

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student CRUD + Loan & Payment Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background-color: #f4f4f9; color: #333; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { text-align: center; }
        form { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        form input, form select { padding: 10px; flex: 1; min-width: 150px; border: 1px solid #ccc; border-radius: 4px; }
        form button { padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        form button.cancel { background-color: #6c757d; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #007bff; color: white; }
        .btn-edit { background-color: #ffc107; color: black; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px; margin-right: 5px; }
        .btn-delete { background-color: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px; margin-right: 5px; }
        .btn-loans { background-color: #17a2b8; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px; margin-right: 5px; }
        .btn-back { background-color: #6c757d; color: white; border: none; padding: 8px 16px; cursor: pointer; border-radius: 4px; margin-bottom: 15px; }
        .summary-box { display: flex; gap: 20px; margin: 15px 0; }
        .summary-card { flex: 1; padding: 15px; border-radius: 6px; text-align: center; }
        .summary-card.paid { background-color: #d4edda; color: #155724; }
        .summary-card.remaining { background-color: #f8d7da; color: #721c24; }
        .summary-card h4 { margin: 0 0 5px 0; font-size: 14px; }
        .summary-card p { margin: 0; font-size: 22px; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">

    <!-- ═══════════════════════════════════════════════════
         VIEW 1: Student list (existing feature, unchanged)
         ═══════════════════════════════════════════════════ -->
    <div id="studentListView">
        <h2>Student Management System (CRUD)</h2>

        <form id="studentForm">
            <input type="hidden" id="studentId">
            <input type="text" id="name" placeholder="Full Name">
            <input type="email" id="email" placeholder="Email Address">
            <input type="text" id="course" placeholder="Course">
            <button type="submit" id="submitBtn">Add Student</button>
            <button type="button" id="cancelBtn" class="cancel" style="display:none;" onclick="resetForm()">Cancel</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="studentTableBody"></tbody>
        </table>
    </div>

    <!-- ═══════════════════════════════════════════════════
         VIEW 2: Loan Workspace — loans for ONE student
         ═══════════════════════════════════════════════════ -->
    <div id="loanWorkspaceView" style="display:none;">
        <button class="btn-back" onclick="backToStudents()">← Back to Students</button>
        <h2>Loans for <span id="loanWorkspaceStudentName"></span></h2>

        <form id="loanForm">
            <input type="hidden" id="loanStudentId">
            <input type="number" id="loanAmount" placeholder="Amount" step="0.01" min="0">
            <select id="loanType">
                <option value="Tuition">Tuition</option>
                <option value="Books">Books</option>
                <option value="Living Expenses">Living Expenses</option>
            </select>
            <select id="loanStatus">
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Disbursed">Disbursed</option>
            </select>
            <button type="submit">Add Loan</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Amount</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="loanTableBody"></tbody>
        </table>
    </div>

    <!-- ═══════════════════════════════════════════════════
         VIEW 3: Payment Workspace — payments for ONE loan
         ═══════════════════════════════════════════════════ -->
    <div id="paymentWorkspaceView" style="display:none;">
        <button class="btn-back" onclick="backToLoans()">← Back to Loans</button>
        <h2>Payments for Loan #<span id="paymentWorkspaceLoanId"></span></h2>

        <div class="summary-box">
            <div class="summary-card paid">
                <h4>Total Paid</h4>
                <p id="totalPaidDisplay">₱0.00</p>
            </div>
            <div class="summary-card remaining">
                <h4>Remaining Balance</h4>
                <p id="remainingBalanceDisplay">₱0.00</p>
            </div>
        </div>

        <form id="paymentForm">
            <input type="hidden" id="paymentLoanId">
            <input type="number" id="paymentAmount" placeholder="Amount" step="0.01" min="0">
            <input type="date" id="paymentDate">
            <select id="paymentMethod">
                <option value="Cash">Cash</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Online Payment">Online Payment</option>
            </select>
            <button type="submit">Add Payment</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="paymentTableBody"></tbody>
        </table>
    </div>

</div>

<script>
    const API_URL = 'api.php';
    const LOANS_API_URL = 'loanapi.php';
    const PAYMENTS_API_URL = 'paymentapi.php';

    // ── Existing student CRUD (unchanged from before) ──────────────
    const form = document.getElementById('studentForm');
    const submitBtn = document.getElementById('submitBtn');
    const cancelBtn = document.getElementById('cancelBtn');

    document.addEventListener("DOMContentLoaded", fetchStudents);

    async function fetchStudents() {
        const response = await fetch(API_URL);
        const students = await response.json();
        const tbody = document.getElementById('studentTableBody');
        tbody.innerHTML = '';

        students.forEach(student => {
            tbody.innerHTML += `
                <tr>
                    <td>${student.id}</td>
                    <td>${student.name}</td>
                    <td>${student.email}</td>
                    <td>${student.course}</td>
                    <td>
                        <button class="btn-edit" onclick="editStudent(${student.id}, '${student.name}', '${student.email}', '${student.course}')">Edit</button>
                        <button class="btn-delete" onclick="deleteStudent(${student.id})">Delete</button>
                        <button class="btn-loans" onclick="openLoanWorkspace(${student.id}, '${student.name}')">Loans</button>
                    </td>
                </tr>
            `;
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('studentId').value;
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const course = document.getElementById('course').value;

        const payload = { name, email, course };
        let method = 'POST';
        if (id) {
            payload.id = id;
            method = 'PUT';
        }

        const response = await fetch(API_URL, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        alert(result.message || result.error);
        resetForm();
        fetchStudents();
    });

    function editStudent(id, name, email, course) {
        document.getElementById('studentId').value = id;
        document.getElementById('name').value = name;
        document.getElementById('email').value = email;
        document.getElementById('course').value = course;
        submitBtn.innerText = "Update Student";
        submitBtn.style.backgroundColor = "#ffc107";
        submitBtn.style.color = "black";
        cancelBtn.style.display = "inline-block";
    }

    async function deleteStudent(id) {
        if (confirm("Are you sure you want to delete this student?")) {
            const response = await fetch(API_URL, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const result = await response.json();
            alert(result.message || result.error);
            fetchStudents();
        }
    }

    function resetForm() {
        form.reset();
        document.getElementById('studentId').value = '';
        submitBtn.innerText = "Add Student";
        submitBtn.style.backgroundColor = "#28a745";
        submitBtn.style.color = "white";
        cancelBtn.style.display = "none";
    }

    // ── View switching ──────────────────────────────────────────────
    function backToStudents() {
        document.getElementById('paymentWorkspaceView').style.display = 'none';
        document.getElementById('loanWorkspaceView').style.display = 'none';
        document.getElementById('studentListView').style.display = 'block';
    }

    function backToLoans() {
        document.getElementById('paymentWorkspaceView').style.display = 'none';
        document.getElementById('loanWorkspaceView').style.display = 'block';
    }

    // ── Loan Workspace ──────────────────────────────────────────────
    let currentStudentId = null;

    function openLoanWorkspace(studentId, studentName) {
        currentStudentId = studentId;
        document.getElementById('loanWorkspaceStudentName').innerText = studentName;
        document.getElementById('loanStudentId').value = studentId;
        document.getElementById('studentListView').style.display = 'none';
        document.getElementById('loanWorkspaceView').style.display = 'block';
        fetchLoans(studentId);
    }

    async function fetchLoans(studentId) {
        const response = await fetch(`${LOANS_API_URL}?student_id=${studentId}`);
        const loans = await response.json();
        const tbody = document.getElementById('loanTableBody');
        tbody.innerHTML = '';

        loans.forEach(loan => {
            tbody.innerHTML += `
                <tr>
                    <td>${loan.id}</td>
                    <td>₱${parseFloat(loan.amount).toFixed(2)}</td>
                    <td>${loan.loan_type}</td>
                    <td>${loan.status}</td>
                    <td>
                        <button class="btn-loans" onclick="openPaymentWorkspace(${loan.id})">Payments</button>
                        <button class="btn-delete" onclick="deleteLoan(${loan.id})">Delete</button>
                    </td>
                </tr>
            `;
        });
    }

    document.getElementById('loanForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            student_id: document.getElementById('loanStudentId').value,
            amount: document.getElementById('loanAmount').value,
            loan_type: document.getElementById('loanType').value,
            status: document.getElementById('loanStatus').value
        };

        const response = await fetch(LOANS_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        alert(result.message || result.error);
        document.getElementById('loanForm').reset();
        fetchLoans(currentStudentId);
    });

    async function deleteLoan(id) {
        if (confirm("Delete this loan? Its payment history will be deleted too.")) {
            const response = await fetch(LOANS_API_URL, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const result = await response.json();
            alert(result.message || result.error);
            fetchLoans(currentStudentId);
        }
    }

    // ── Payment Workspace ────────────────────────────────────────────
    let currentLoanId = null;

    function openPaymentWorkspace(loanId) {
        currentLoanId = loanId;
        document.getElementById('paymentWorkspaceLoanId').innerText = loanId;
        document.getElementById('paymentLoanId').value = loanId;
        document.getElementById('loanWorkspaceView').style.display = 'none';
        document.getElementById('paymentWorkspaceView').style.display = 'block';
        fetchPayments(loanId);
    }

    async function fetchPayments(loanId) {
        const response = await fetch(`${PAYMENTS_API_URL}?loan_id=${loanId}`);
        const data = await response.json();

        document.getElementById('totalPaidDisplay').innerText = `₱${parseFloat(data.total_paid).toFixed(2)}`;
        document.getElementById('remainingBalanceDisplay').innerText = `₱${parseFloat(data.remaining_balance).toFixed(2)}`;

        const tbody = document.getElementById('paymentTableBody');
        tbody.innerHTML = '';

        data.payments.forEach(payment => {
            tbody.innerHTML += `
                <tr>
                    <td>${payment.id}</td>
                    <td>₱${parseFloat(payment.amount).toFixed(2)}</td>
                    <td>${payment.payment_date}</td>
                    <td>${payment.payment_method}</td>
                    <td>
                        <button class="btn-delete" onclick="deletePayment(${payment.id})">Delete</button>
                    </td>
                </tr>
            `;
        });
    }

    document.getElementById('paymentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            loan_id: document.getElementById('paymentLoanId').value,
            amount: document.getElementById('paymentAmount').value,
            payment_date: document.getElementById('paymentDate').value,
            payment_method: document.getElementById('paymentMethod').value
        };

        const response = await fetch(PAYMENTS_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        alert(result.message || result.error);
        document.getElementById('paymentForm').reset();
        fetchPayments(currentLoanId);
    });


    async function deletePayment(id) {
        if (confirm("Delete this payment record?")) {
            const response = await fetch(PAYMENTS_API_URL, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const result = await response.json();
            alert(result.message || result.error);
            fetchPayments(currentLoanId);
        }
    }
</script>
</body>
</html>
