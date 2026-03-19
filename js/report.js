async function generateReport(type) {
    try {
        let data = [];
        let filename = '';
        
        switch(type) {
            case 'students':
                const studentsResult = await api.getStudents();
                data = studentsResult.data || [];
                filename = 'students_report.csv';
                break;
                
            case 'payments':
                const paymentsResult = await api.getPayments();
                data = (paymentsResult.data || []).map(p => ({
                    'Receipt No': p.RECEIPT_NUMBER,
                    'Student': p.STUDENT_NAME,
                    'Date': formatDate(p.PAYMENT_DATE),
                    'Type': p.PAYMENT_TYPE,
                    'Amount': p.AMOUNT,
                    'Method': p.PAYMENT_METHOD,
                    'Status': p.STATUS
                }));
                filename = 'payments_report.csv';
                break;
                
            case 'occupancy':
                const roomsResult = await api.getRooms();
                data = (roomsResult.data || []).map(r => ({
                    'Hostel': r.HOSTEL_NAME,
                    'Room No': r.ROOM_NUMBER,
                    'Capacity': r.CAPACITY,
                    'Occupancy': r.OCCUPANCY,
                    'Status': r.STATUS,
                    'Rent': r.RENT_AMOUNT
                }));
                filename = 'occupancy_report.csv';
                break;
        }
        
        if (data.length > 0) {
            exportToCSV(data, filename);
            showAlert(`${type} report generated successfully`, 'success');
        } else {
            showAlert('No data to export', 'warning');
        }
        
    } catch (error) {
        console.error('Error generating report:', error);
        showAlert('Error generating report', 'danger');
    }
}
