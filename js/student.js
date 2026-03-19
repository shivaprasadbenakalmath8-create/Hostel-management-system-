document.addEventListener('DOMContentLoaded', function() {
    loadStudentData();
    loadTodayMenu();
    loadRecentPayments();
    loadNotices();
});

async function loadStudentData() {
    try {
        // Get current user from session
        const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
        
        if (currentUser.user_id) {
            // Get student details
            const result = await api.getStudents();
            const students = result.data || [];
            const student = students.find(s => s.USER_ID == currentUser.user_id);
            
            if (student) {
                document.getElementById('studentName').textContent = student.FULL_NAME;
                document.getElementById('studentRegNo').textContent = student.REG_NUMBER;
                
                // Get allocation
                const allocResult = await api.request(`allocations.php?student_id=${student.STUDENT_ID}`);
                const allocations = allocResult.data || [];
                const activeAlloc = allocations.find(a => a.STATUS === 'active');
                
                if (activeAlloc) {
                    document.getElementById('studentRoom').textContent = activeAlloc.ROOM_NUMBER;
                    document.getElementById('studentHostel').textContent = activeAlloc.HOSTEL_NAME;
                    document.getElementById('allocationDate').textContent = formatDate(activeAlloc.ALLOCATION_DATE);
                }
                
                // Get payment due
                const paymentResult = await api.request(`payments.php?student_id=${student.STUDENT_ID}`);
                const payments = paymentResult.data || [];
                const totalPaid = payments
                    .filter(p => p.STATUS === 'completed')
                    .reduce((sum, p) => sum + parseFloat(p.AMOUNT), 0);
                
                // Assuming monthly fee is 5000
                const monthlyFee = 5000;
                const monthsSinceJoining = student.YEAR_OF_STUDY * 12;
                const expectedTotal = monthlyFee * monthsSinceJoining;
                const due = Math.max(0, expectedTotal - totalPaid);
                
                document.getElementById('studentDue').textContent = formatCurrency(due);
            }
        }
    } catch (error) {
        console.error('Error loading student data:', error);
    }
}

async function loadTodayMenu() {
    try {
        const result = await api.request('mess.php?today=true');
        
        if (result.success) {
            const menu = result.data;
            document.getElementById('breakfast').textContent = menu.BREAKFAST || '-';
            document.getElementById('lunch').textContent = menu.LUNCH || '-';
            document.getElementById('snacks').textContent = menu.SNACKS || '-';
            document.getElementById('dinner').textContent = menu.DINNER || '-';
        }
    } catch (error) {
        console.error('Error loading menu:', error);
    }
}

async function loadRecentPayments() {
    try {
        const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
        
        if (currentUser.user_id) {
            const studentsResult = await api.getStudents();
            const students = studentsResult.data || [];
            const student = students.find(s => s.USER_ID == currentUser.user_id);
            
            if (student) {
                const paymentResult = await api.request(`payments.php?student_id=${student.STUDENT_ID}`);
                const payments = paymentResult.data || [];
                const recent = payments.slice(0, 5);
                
                const tbody = document.getElementById('studentRecentPayments');
                
                if (recent.length > 0) {
                    tbody.innerHTML = recent.map(payment => `
                        <tr>
                            <td>${formatDate(payment.PAYMENT_DATE)}</td>
                            <td>${payment.PAYMENT_TYPE}</td>
                            <td>${formatCurrency(payment.AMOUNT)}</td>
                            <td>
                                <span class="badge bg-${payment.STATUS === 'completed' ? 'success' : 'warning'}">
                                    ${payment.STATUS}
                                </span>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center">No payments found</td></tr>';
                }
            }
        }
    } catch (error) {
        console.error('Error loading payments:', error);
    }
}

function loadNotices() {
    // Sample notices - in real app, fetch from database
    const notices = [
        { title: 'Maintenance Work', date: '2024-01-15', message: 'Water supply will be suspended on Sunday' },
        { title: 'Mess Timings', date: '2024-01-14', message: 'Dinner timing changed to 7:30 PM' },
        { title: 'Fee Reminder', date: '2024-01-10', message: 'Last date for fee payment is Jan 20' }
    ];
    
    const noticesList = document.getElementById('noticesList');
    
    if (notices.length > 0) {
        noticesList.innerHTML = notices.map(notice => `
            <div class="list-group-item">
                <div class="d-flex justify-content-between">
                    <h6 class="mb-1">${notice.title}</h6>
                    <small class="text-muted">${formatDate(notice.date)}</small>
                </div>
                <p class="mb-1">${notice.message}</p>
            </div>
        `).join('');
    } else {
        noticesList.innerHTML = '<div class="text-center text-muted">No notices available</div>';
    }
                      }
