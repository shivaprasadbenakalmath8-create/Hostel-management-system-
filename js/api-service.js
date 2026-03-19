class APIService {
    constructor() {
        this.baseURL = '/hostel-management-system/api/';
    }
    
    async request(endpoint, method = 'GET', data = null) {
        const url = this.baseURL + endpoint;
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(url, options);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.message || 'API request failed');
            }
            
            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
    
    // Auth
    async login(username, password, role) {
        return this.request('login.php', 'POST', { username, password, role });
    }
    
    // Hostels
    async getHostels() {
        return this.request('hostels.php');
    }
    
    async getHostel(id) {
        return this.request(`hostels.php?id=${id}`);
    }
    
    async createHostel(data) {
        return this.request('hostels.php', 'POST', data);
    }
    
    async updateHostel(id, data) {
        return this.request(`hostels.php?id=${id}`, 'PUT', data);
    }
    
    async deleteHostel(id) {
        return this.request(`hostels.php?id=${id}`, 'DELETE');
    }
    
    // Rooms
    async getRooms() {
        return this.request('rooms.php');
    }
    
    async getRoomsByHostel(hostelId) {
        return this.request(`rooms.php?hostel_id=${hostelId}`);
    }
    
    async createRoom(data) {
        return this.request('rooms.php', 'POST', data);
    }
    
    async updateRoom(id, data) {
        return this.request(`rooms.php?id=${id}`, 'PUT', data);
    }
    
    async deleteRoom(id) {
        return this.request(`rooms.php?id=${id}`, 'DELETE');
    }
    
    // Students
    async getStudents() {
        return this.request('students.php');
    }
    
    async getStudent(id) {
        return this.request(`students.php?id=${id}`);
    }
    
    async createStudent(data) {
        return this.request('students.php', 'POST', data);
    }
    
    async updateStudent(id, data) {
        return this.request(`students.php?id=${id}`, 'PUT', data);
    }
    
    async deleteStudent(id) {
        return this.request(`students.php?id=${id}`, 'DELETE');
    }
    
    // Allocations
    async getAllocations() {
        return this.request('allocations.php');
    }
    
    async getAllocationsByStudent(studentId) {
        return this.request(`allocations.php?student_id=${studentId}`);
    }
    
    async allocateRoom(data) {
        return this.request('allocations.php', 'POST', data);
    }
    
    async deallocateRoom(id) {
        return this.request(`allocations.php?id=${id}`, 'DELETE');
    }
    
    // Staff
    async getStaff() {
        return this.request('staff.php');
    }
    
    async createStaff(data) {
        return this.request('staff.php', 'POST', data);
    }
    
    async updateStaff(id, data) {
        return this.request(`staff.php?id=${id}`, 'PUT', data);
    }
    
    async deleteStaff(id) {
        return this.request(`staff.php?id=${id}`, 'DELETE');
    }
    
    // Mess Menu
    async getMessMenu() {
        return this.request('mess.php');
    }
    
    async updateMessMenu(data) {
        return this.request('mess.php', 'POST', data);
    }
    
    // Payments
    async getPayments() {
        return this.request('payments.php');
    }
    
    async getPaymentsByStudent(studentId) {
        return this.request(`payments.php?student_id=${studentId}`);
    }
    
    async createPayment(data) {
        return this.request('payments.php', 'POST', data);
    }
    
    async getPaymentReceipt(id) {
        return this.request(`payments.php?id=${id}&receipt=true`);
    }
    
    // Dashboard
    async getDashboardStats() {
        return this.request('dashboard.php');
    }
}

const api = new APIService();
