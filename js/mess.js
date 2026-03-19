document.addEventListener('DOMContentLoaded', function() {
    loadWeeklyMenu();
    loadMessStats();
});

async function loadWeeklyMenu() {
    try {
        const result = await api.getMessMenu();
        
        if (result.success) {
            const menu = result.data || [];
            
            menu.forEach(item => {
                const day = item.DAY_OF_WEEK.toLowerCase();
                
                // Set values for each day
                document.getElementById(`${day}-breakfast`).textContent = item.BREAKFAST || '-';
                document.getElementById(`${day}-lunch`).textContent = item.LUNCH || '-';
                document.getElementById(`${day}-snacks`).textContent = item.SNACKS || '-';
                document.getElementById(`${day}-dinner`).textContent = item.DINNER || '-';
                document.getElementById(`${day}-special`).textContent = item.SPECIAL_ITEM || '-';
            });
        }
    } catch (error) {
        console.error('Error loading menu:', error);
    }
}

async function loadMessStats() {
    try {
        // Get total students
        const studentsResult = await api.getStudents();
        document.getElementById('totalMessStudents').textContent = studentsResult.data?.length || 0;
        
        // Calculate monthly mess bill (assuming ₹2500 per student)
        const totalStudents = studentsResult.data?.length || 0;
        const monthlyBill = totalStudents * 2500;
        document.getElementById('monthlyMessBill').textContent = formatCurrency(monthlyBill);
        
        // Sample average rating
        document.getElementById('avgRating').textContent = '4.2/5';
        
    } catch (error) {
        console.error('Error loading mess stats:', error);
    }
}

async function saveMenu() {
    // This would collect data from a form and save all days' menus
    const menuData = [];
    const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    
    days.forEach(day => {
        menuData.push({
            day_of_week: day.charAt(0).toUpperCase() + day.slice(1),
            breakfast: document.getElementById(`${day}-breakfast-input`)?.value || '',
            lunch: document.getElementById(`${day}-lunch-input`)?.value || '',
            snacks: document.getElementById(`${day}-snacks-input`)?.value || '',
            dinner: document.getElementById(`${day}-dinner-input`)?.value || '',
            special_item: document.getElementById(`${day}-special-input`)?.value || ''
        });
    });
    
    try {
        const result = await api.updateMessMenu({ weekly: true, menu: menuData });
        
        if (result.success) {
            showAlert('Menu updated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editMenuModal')).hide();
            loadWeeklyMenu();
        }
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
