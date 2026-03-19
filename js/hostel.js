document.addEventListener('DOMContentLoaded', function() {
    loadHostels();
    loadRooms();
    loadAllocations();
    loadHostelDropdown();
    loadStudentsDropdown();
    
    // Setup modal event listeners
    const allocateModal = document.getElementById('allocateRoomModal');
    if (allocateModal) {
        allocateModal.addEventListener('show.bs.modal', loadAvailableRooms);
    }
});

async function loadHostels() {
    try {
        const result = await api.getHostels();
        const tbody = document.getElementById('hostelsList');
        
        if (!tbody) return;
        
        if (result.data && result.data.length > 0) {
            tbody.innerHTML = result.data.map(hostel => `
                <tr>
                    <td>${hostel.HOSTEL_ID}</td>
                    <td>${hostel.HOSTEL_NAME}</td>
                    <td>${hostel.TOTAL_ROOMS}</td>
                    <td>${hostel.WARDEN_NAME}</td>
                    <td>${hostel.CONTACT_NUMBER}</td>
                    <td>${hostel.ADDRESS || 'N/A'}</td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="editHostel(${hostel.HOSTEL_ID})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteHostel(${hostel.HOSTEL_ID})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hostels found</td></tr>';
        }
    } catch (error) {
        console.error('Error loading hostels:', error);
        showAlert('Error loading hostels', 'danger');
    }
}

async function loadRooms() {
    try {
        const result = await api.getRooms();
        const tbody = document.getElementById('roomsList');
        
        if (!tbody) return;
        
        if (result.data && result.data.length > 0) {
            tbody.innerHTML = result.data.map(room => {
                const statusClass = {
                    'available': 'success',
                    'occupied': 'warning',
                    'maintenance': 'danger'
                }[room.STATUS] || 'secondary';
                
                return `
                    <tr>
                        <td>${room.ROOM_ID}</td>
                        <td>${room.HOSTEL_NAME}</td>
                        <td>${room.ROOM_NUMBER}</td>
                        <td>${room.FLOOR || 'N/A'}</td>
                        <td>${room.CAPACITY}</td>
                        <td>${room.OCCUPANCY}/${room.CAPACITY}</td>
                        <td>
                            <span class="badge bg-${statusClass}">
                                ${room.STATUS}
                            </span>
                        </td>
                        <td>${formatCurrency(room.RENT_AMOUNT)}</td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="editRoom(${room.ROOM_ID})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteRoom(${room.ROOM_ID})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center">No rooms found</td></tr>';
        }
    } catch (error) {
        console.error('Error loading rooms:', error);
    }
}

async function loadAllocations() {
    try {
        const result = await api.getAllocations();
        const tbody = document.getElementById('allocationsList');
        
        if (!tbody) return;
        
        if (result.data && result.data.length > 0) {
            tbody.innerHTML = result.data.map(allocation => `
                <tr>
                    <td>${allocation.ALLOCATION_ID}</td>
                    <td>${allocation.STUDENT_NAME}</td>
                    <td>${allocation.ROOM_NUMBER} (${allocation.HOSTEL_NAME})</td>
                    <td>${formatDate(allocation.ALLOCATION_DATE)}</td>
                    <td>${formatDate(allocation.END_DATE)}</td>
                    <td>
                        <span class="badge bg-${allocation.STATUS === 'active' ? 'success' : 'secondary'}">
                            ${allocation.STATUS}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="deallocateRoom(${allocation.ALLOCATION_ID})">
                            Deallocate
                        </button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No allocations found</td></tr>';
        }
    } catch (error) {
        console.error('Error loading allocations:', error);
    }
}

async function loadHostelDropdown() {
    try {
        const result = await api.getHostels();
        const select = document.getElementById('roomHostel');
        
        if (select && result.data) {
            select.innerHTML = '<option value="">Choose Hostel</option>' +
                result.data.map(hostel => 
                    `<option value="${hostel.HOSTEL_ID}">${hostel.HOSTEL_NAME}</option>`
                ).join('');
        }
    } catch (error) {
        console.error('Error loading hostel dropdown:', error);
    }
}

async function loadStudentsDropdown() {
    try {
        const result = await api.getStudents();
        const select = document.getElementById('studentSelect');
        
        if (select && result.data) {
            select.innerHTML = '<option value="">Choose Student</option>' +
                result.data.map(student => 
                    `<option value="${student.STUDENT_ID}">${student.FULL_NAME} (${student.REG_NUMBER})</option>`
                ).join('');
        }
    } catch (error) {
        console.error('Error loading students dropdown:', error);
    }
}

async function loadAvailableRooms() {
    try {
        const result = await api.getRooms();
        const select = document.getElementById('roomSelect');
        
        if (select && result.data) {
            const availableRooms = result.data.filter(room => room.STATUS === 'available');
            select.innerHTML = '<option value="">Choose Room</option>' +
                availableRooms.map(room => 
                    `<option value="${room.ROOM_ID}">${room.HOSTEL_NAME} - Room ${room.ROOM_NUMBER} (Capacity: ${room.CAPACITY})</option>`
                ).join('');
        }
    } catch (error) {
        console.error('Error loading rooms dropdown:', error);
    }
}

async function saveHostel() {
    const hostelData = {
        hostel_name: document.getElementById('hostelName').value,
        total_rooms: document.getElementById('totalRooms').value,
        warden_name: document.getElementById('wardenName').value,
        contact_number: document.getElementById('contactNumber').value,
        address: document.getElementById('hostelAddress').value
    };
    
    try {
        const result = await api.createHostel(hostelData);
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('addHostelModal')).hide();
            await loadHostels();
            showAlert('Hostel added successfully', 'success');
            document.getElementById('addHostelForm').reset();
        }
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function saveRoom() {
    const roomData = {
        hostel_id: document.getElementById('roomHostel').value,
        room_number: document.getElementById('roomNumber').value,
        floor: document.getElementById('roomFloor').value,
        capacity: document.getElementById('capacity').value,
        rent_amount: document.getElementById('rentAmount').value
    };
    
    try {
        const result = await api.createRoom(roomData);
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('addRoomModal')).hide();
            await loadRooms();
            showAlert('Room added successfully', 'success');
            document.getElementById('addRoomForm').reset();
        }
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function allocateRoom() {
    const allocationData = {
        student_id: document.getElementById('studentSelect').value,
        room_id: document.getElementById('roomSelect').value,
        end_date: document.getElementById('endDate').value
    };
    
    try {
        const result = await api.allocateRoom(allocationData);
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('allocateRoomModal')).hide();
            await Promise.all([loadAllocations(), loadRooms()]);
            showAlert('Room allocated successfully', 'success');
            document.getElementById('allocateRoomForm').reset();
        }
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function deleteHostel(id) {
    if (confirm('Are you sure you want to delete this hostel? This action cannot be undone.')) {
        try {
            const result = await api.deleteHostel(id);
            
            if (result.success) {
                await loadHostels();
                showAlert('Hostel deleted successfully', 'success');
            }
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    }
}

async function deleteRoom(id) {
    if (confirm('Are you sure you want to delete this room?')) {
        try {
            const result = await api.deleteRoom(id);
            
            if (result.success) {
                await loadRooms();
                showAlert('Room deleted successfully', 'success');
            }
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    }
}

async function deallocateRoom(id) {
    if (confirm('Are you sure you want to deallocate this room?')) {
        try {
            const result = await api.deallocateRoom(id);
            
            if (result.success) {
                await Promise.all([loadAllocations(), loadRooms()]);
                showAlert('Room deallocated successfully', 'success');
            }
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    }
}
