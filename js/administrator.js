// Administrator Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    loadRecentAllocations();
    loadRecentPayments();
    initializeCharts();
    
    // Set user name from session
    const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    if (currentUser.display_name) {
        document.getElementById('userName').textContent = currentUser.display_name;
    }
});

// Load dashboard statistics
async function loadDashboardData() {
    try {
        // Get all hostels
        const hostelsResult = await api.getHostels();
        document.getElementById('totalHostels').textContent = hostelsResult.data?.length || 0;
        
        // Get all rooms
        const roomsResult = await api.getRooms();
        document.getElementById('totalRooms').textContent = roomsResult.data?.length || 0;
        
        // Get all students
        const studentsResult = await api.getStudents();
        document.getElementById('totalStudents').textContent = studentsResult.data?.length || 0;
        
        // Calculate monthly revenue
        const paymentsResult = await api.getPayments();
        if (paymentsResult.data) {
            const currentMonth = new Date().getMonth();
            const monthlyTotal = paymentsResult.data
                .filter(payment => new Date(payment.PAYMENT_DATE).getMonth() === currentMonth)
                .reduce((sum, payment) => sum + parseFloat(payment.AMOUNT), 0);
            
            document.getElementById('monthlyRevenue').textContent = formatCurrency(monthlyTotal);
        }
    } catch (error) {
        console.error('Error loading dashboard data:', error);
    }
}

// Load recent allocations
async function loadRecentAllocations() {
    try {
        const result = await api.getAllocations();
        const tbody = document.getElementById('recentAllocations');
        
        if (result.data && result.data.length > 0) {
            const recent = result.data.slice(0, 5);
            tbody.innerHTML = recent.map(allocation => `
                <tr>
                    <td>${allocation.STUDENT_NAME}</td>
                    <td>${allocation.ROOM_NUMBER}</td>
                    <td>${formatDate(allocation.ALLOCATION_DATE)}</td>
                    <td>
                        <span class="badge bg-${allocation.STATUS === 'active' ? 'success' : 'secondary'}">
                            ${allocation.STATUS}
                        </span>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center">No allocations found</td></tr>';
        }
    } catch (error) {
        console.error('Error loading recent allocations:', error);
    }
}

// Load recent payments
async function loadRecentPayments() {
    try {
        const result = await api.getPayments();
        const tbody = document.getElementById('recentPayments');
        
        if (result.data && result.data.length > 0) {
            const recent = result.data.slice(0, 5);
            tbody.innerHTML = recent.map(payment => `
                <tr>
                    <td>${payment.STUDENT_NAME}</td>
                    <td>${formatCurrency(payment.AMOUNT)}</td>
                    <td>${formatDate(payment.PAYMENT_DATE)}</td>
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
    } catch (error) {
        console.error('Error loading recent payments:', error);
    }
}

// Initialize charts
async function initializeCharts() {
    try {
        // Occupancy Chart
        const roomsResult = await api.getRooms();
        const rooms = roomsResult.data || [];
        
        const available = rooms.filter(r => r.STATUS === 'available').length;
        const occupied = rooms.filter(r => r.STATUS === 'occupied').length;
        const maintenance = rooms.filter(r => r.STATUS === 'maintenance').length;
        
        new Chart(document.getElementById('occupancyChart'), {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Occupied', 'Maintenance'],
                datasets: [{
                    data: [available, occupied, maintenance],
                    backgroundColor: ['#198754', '#ffc107', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Revenue Chart
        const paymentsResult = await api.getPayments();
        const payments = paymentsResult.data || [];
        
        // Group by month
        const monthlyData = {};
        payments.forEach(payment => {
            const date = new Date(payment.PAYMENT_DATE);
            const monthYear = date.toLocaleString('default', { month: 'short', year: 'numeric' });
            monthlyData[monthYear] = (monthlyData[monthYear] || 0) + parseFloat(payment.AMOUNT);
        });
        
        const months = Object.keys(monthlyData).slice(-6);
        const amounts = Object.values(monthlyData).slice(-6);
        
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Revenue',
                    data: amounts,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value;
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error initializing charts:', error);
    }
          }
