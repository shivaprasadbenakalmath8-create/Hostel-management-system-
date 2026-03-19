document.addEventListener('DOMContentLoaded', function() {
    loadPayments();
    loadPaymentStats();
    loadStudentDropdown();
    
    // Setup filter listeners
    document.getElementById('fromDate').addEventListener('change', filterPayments);
    document.getElementById('toDate').addEventListener('change', filterPayments);
    document.getElementById('paymentType').addEventListener('change', filterPayments);
    document.getElementById('paymentStatus').addEventListener('change', filterPayments);
});

async function loadPayments() {
    try {
        const result = await api.getPayments();
        displayPayments(result.data || []);
    } catch (error) {
        console.error('Error loading payments:', error);
    }
}

async function loadPaymentStats() {
    try {
        const result = await api.request('payments.php?stats=true');
        
        if (result.success) {
            const stats = result.data;
            document.getElementById('totalCollected').textContent = formatCurrency(stats.total_collected || 0);
            document.getElementById('monthlyCollected').textContent = formatCurrency(stats.monthly_collected || 0);
            document.getElementById('totalTransactions').textContent = stats.total_transactions || 0;
            
            // Calculate pending amount (sample logic)
            const studentsResult = await api.getStudents();
            const totalStudents = studentsResult.data?.length || 0;
            const pendingAmount = (totalStudents * 5000) - (stats.monthly_collected || 0);
            document.getElementById('pendingAmount').textContent = formatCurrency(Math.max(0, pendingAmount));
        }
    } catch (error) {
        console.error('Error loading payment stats:', error);
    }
}

async function loadStudentDropdown() {
    try {
        const result = await api.getStudents();
        const select = document.getElementById('paymentStudent');
        
        if (select && result.data) {
            select.innerHTML = '<option value="">Select Student</option>' +
                result.data.map(student => 
                    `<option value="${student.STUDENT_ID}">${student.FULL_NAME} (${student.REG_NUMBER})</option>`
                ).join('');
        }
    } catch (error) {
        console.error('Error loading student dropdown:', error);
    }
}

function displayPayments(payments) {
    const tbody = document.getElementById('paymentsList');
    
    if (payments.length > 0) {
        tbody.innerHTML = payments.map(payment => `
            <tr>
                <td>${payment.RECEIPT_NUMBER || 'N/A'}</td>
                <td>${payment.STUDENT_NAME}</td>
                <td>${formatDate(payment.PAYMENT_DATE)}</td>
                <td>${payment.PAYMENT_TYPE}</td>
                <td>${formatCurrency(payment.AMOUNT)}</td>
                <td>${payment.PAYMENT_METHOD}</td>
                <td>
                    <span class="badge bg-${payment.STATUS === 'completed' ? 'success' : 'warning'}">
                        ${payment.STATUS}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="printReceipt(${payment.PAYMENT_ID})">
                        <i class="fas fa-print"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No payments found</td></tr>';
    }
}

async function filterPayments() {
    const filters = {
        from_date: document.getElementById('fromDate').value,
        to_date: document.getElementById('toDate').value,
        payment_type: document.getElementById('paymentType').value,
        status: document.getElementById('paymentStatus').value
    };
    
    // Remove empty filters
    Object.keys(filters).forEach(key => {
        if (!filters[key]) delete filters[key];
    });
    
    try {
        const result = await api.request('payments.php', 'POST', { filters });
        
        if (result.success) {
            displayPayments(result.data || []);
        }
    } catch (error) {
        console.error('Error filtering payments:', error);
    }
}

function resetFilters() {
    document.getElementById('fromDate').value = '';
    document.getElementById('toDate').value = '';
    document.getElementById('paymentType').value = '';
    document.getElementById('paymentStatus').value = '';
    loadPayments();
}

async function savePayment() {
    const paymentData = {
        student_id: document.getElementById('paymentStudent').value,
        payment_type: document.getElementById('paymentTypeSelect').value,
        amount: document.getElementById('paymentAmount').value,
        payment_method: document.getElementById('paymentMethod').value,
        description: document.getElementById('paymentDescription').value
    };
    
    // Validate
    if (!paymentData.student_id) {
        showAlert('Please select a student', 'warning');
        return;
    }
    
    if (!paymentData.amount || paymentData.amount <= 0) {
        showAlert('Please enter a valid amount', 'warning');
        return;
    }
    
    try {
        const result = await api.createPayment(paymentData);
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('addPaymentModal')).hide();
            await Promise.all([loadPayments(), loadPaymentStats()]);
            showAlert(`Payment recorded successfully. Receipt: ${result.data.receipt_number}`, 'success');
            document.getElementById('paymentForm').reset();
        }
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function printReceipt(paymentId) {
    try {
        const result = await api.getPaymentReceipt(paymentId);
        
        if (result.success) {
            const receipt = result.data;
            
            // Create printable receipt
            const receiptContent = `
                <div style="text-align: center; margin-bottom: 20px;">
                    <h2>Hostel Management System</h2>
                    <h4>Payment Receipt</h4>
                </div>
                <div style="margin-bottom: 20px;">
                    <p><strong>Receipt No:</strong> ${receipt.RECEIPT_NUMBER}</p>
                    <p><strong>Date:</strong> ${formatDate(receipt.PAYMENT_DATE)}</p>
                    <p><strong>Student Name:</strong> ${receipt.FULL_NAME}</p>
                    <p><strong>Registration No:</strong> ${receipt.REG_NUMBER}</p>
                    <p><strong>Course:</strong> ${receipt.COURSE}</p>
                </div>
                <div style="margin-bottom: 20px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="background-color: #f2f2f2;">
                            <th style="padding: 10px; border: 1px solid #ddd;">Description</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Amount</th>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ddd;">${receipt.PAYMENT_TYPE}</td>
                            <td style="padding: 10px; border: 1px solid #ddd;">${formatCurrency(receipt.AMOUNT)}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ddd;"><strong>Total</strong></td>
                            <td style="padding: 10px; border: 1px solid #ddd;"><strong>${formatCurrency(receipt.AMOUNT)}</strong></td>
                        </tr>
                    </table>
                </div>
                <div style="margin-top: 30px;">
                    <p><strong>Payment Method:</strong> ${receipt.PAYMENT_METHOD}</p>
                    <p><strong>Status:</strong> ${receipt.STATUS}</p>
                </div>
                <div style="margin-top: 50px; text-align: right;">
                    <p>Authorized Signature</p>
                </div>
            `;
            
            printContent(receiptContent);
        }
    } catch (error) {
        console.error('Error printing receipt:', error);
        showAlert('Error generating receipt', 'danger');
    }
}

function printContent(content) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Payment Receipt</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    @media print {
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                ${content}
                <script>
                    window.onload = function() {
                        window.print();
                        window.onafterprint = function() { window.close(); }
                    }
                </script>
            </body>
        </html>
    `);
    printWindow.document.close();
    }
