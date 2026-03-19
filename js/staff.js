document.addEventListener('DOMContentLoaded', function() {
    loadStaffStats();
    loadRecentActivities();
    
    // Set staff role
    const currentUser = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    if (currentUser.role) {
        document.getElementById('staffRole').textContent = 
            currentUser.role.charAt(0).toUpperCase() + currentUser.role.slice(1);
    }
});

async function loadStaffStats() {
    try {
        // Get total students
        const studentsResult = await api.getStudents();
        document.getElementById('totalStudents').textContent = studentsResult.data?.length || 0;
        
        // Get available rooms
        const roomsResult = await api.getRooms();
        const availableRooms = (roomsResult.data || []).filter(r => r.STATUS === 'available').length;
        document.getElementById('availableRooms').textContent = availableRooms;
        
        // Sample pending complaints (in real app, fetch from database)
        document.getElementById('pendingComplaints').textContent = '3';
        
        // Sample attendance (in real app, fetch from database)
        document.getElementById('todayAttendance').textContent = '85%';
        
    } catch (error) {
        console.error('Error loading staff stats:', error);
    }
}

async function loadRecentActivities() {
    try {
        // Get recent allocations
        const allocResult = await api.getAllocations();
        const allocations = (allocResult.data || []).slice(0, 5);
        
        const tbody = document.getElementById('recentActivities');
        
        if (allocations.length > 0) {
            tbody.innerHTML = allocations.map(alloc => `
                <tr>
                    <td>${alloc.STUDENT_NAME}</td>
                    <td>Room allocated: ${alloc.ROOM_NUMBER}</td>
                    <td>${formatDateTime(alloc.ALLOCATION_DATE)}</td>
                    <td>
                        <span class="badge bg-success">Completed</span>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center">No recent activities</td></tr>';
        }
    } catch (error) {
        console.error('Error loading activities:', error);
    }
          }
