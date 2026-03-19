-- Create sequences
CREATE SEQUENCE seq_users START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_students START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_hostels START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_rooms START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_allocations START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_staff START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_menu START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_payments START WITH 1 INCREMENT BY 1;

-- Create tables
CREATE TABLE users (
    user_id NUMBER PRIMARY KEY,
    username VARCHAR2(50) UNIQUE NOT NULL,
    password VARCHAR2(100) NOT NULL,
    email VARCHAR2(100),
    role VARCHAR2(20) CHECK (role IN ('admin', 'staff', 'student')),
    created_date DATE DEFAULT SYSDATE
);

CREATE TABLE students (
    student_id NUMBER PRIMARY KEY,
    user_id NUMBER REFERENCES users(user_id),
    reg_number VARCHAR2(20) UNIQUE NOT NULL,
    full_name VARCHAR2(100) NOT NULL,
    course VARCHAR2(50),
    year_of_study NUMBER,
    phone VARCHAR2(15),
    address VARCHAR2(200),
    parent_name VARCHAR2(100),
    parent_phone VARCHAR2(15),
    created_date DATE DEFAULT SYSDATE
);

CREATE TABLE hostels (
    hostel_id NUMBER PRIMARY KEY,
    hostel_name VARCHAR2(50) NOT NULL,
    total_rooms NUMBER,
    warden_name VARCHAR2(100),
    contact_number VARCHAR2(15),
    address VARCHAR2(200),
    created_date DATE DEFAULT SYSDATE
);

CREATE TABLE rooms (
    room_id NUMBER PRIMARY KEY,
    hostel_id NUMBER REFERENCES hostels(hostel_id),
    room_number VARCHAR2(10) NOT NULL,
    floor NUMBER,
    capacity NUMBER,
    occupancy NUMBER DEFAULT 0,
    status VARCHAR2(20) DEFAULT 'available',
    rent_amount NUMBER(10,2),
    created_date DATE DEFAULT SYSDATE
);

CREATE TABLE allocations (
    allocation_id NUMBER PRIMARY KEY,
    student_id NUMBER REFERENCES students(student_id),
    room_id NUMBER REFERENCES rooms(room_id),
    allocation_date DATE DEFAULT SYSDATE,
    end_date DATE,
    status VARCHAR2(20) DEFAULT 'active',
    created_date DATE DEFAULT SYSDATE
);

CREATE TABLE staff (
    staff_id NUMBER PRIMARY KEY,
    user_id NUMBER REFERENCES users(user_id),
    staff_number VARCHAR2(20) UNIQUE NOT NULL,
    full_name VARCHAR2(100) NOT NULL,
    designation VARCHAR2(50),
    department VARCHAR2(50),
    phone VARCHAR2(15),
    email VARCHAR2(100),
    joining_date DATE,
    salary NUMBER(10,2),
    created_date DATE DEFAULT SYSDATE
);

CREATE TABLE mess_menu (
    menu_id NUMBER PRIMARY KEY,
    day_of_week VARCHAR2(10),
    breakfast VARCHAR2(200),
    lunch VARCHAR2(200),
    snacks VARCHAR2(200),
    dinner VARCHAR2(200),
    special_item VARCHAR2(200),
    created_date DATE DEFAULT SYSDATE
);

CREATE TABLE payments (
    payment_id NUMBER PRIMARY KEY,
    student_id NUMBER REFERENCES students(student_id),
    payment_date DATE DEFAULT SYSDATE,
    amount NUMBER(10,2),
    payment_type VARCHAR2(50),
    payment_method VARCHAR2(20),
    status VARCHAR2(20) DEFAULT 'completed',
    receipt_number VARCHAR2(50),
    description VARCHAR2(200),
    created_date DATE DEFAULT SYSDATE
);

-- Insert sample data
INSERT INTO users VALUES (seq_users.NEXTVAL, 'admin', 'admin123', 'admin@hms.com', 'admin', SYSDATE);
INSERT INTO users VALUES (seq_users.NEXTVAL, 'john_staff', 'staff123', 'john@hms.com', 'staff', SYSDATE);
INSERT INTO users VALUES (seq_users.NEXTVAL, 'james_student', 'student123', 'james@student.com', 'student', SYSDATE);

INSERT INTO hostels VALUES (seq_hostels.NEXTVAL, 'Boys Hostel - A', 50, 'Mr. Sharma', '9876543210', 'Main Campus', SYSDATE);
INSERT INTO hostels VALUES (seq_hostels.NEXTVAL, 'Girls Hostel - B', 45, 'Mrs. Patel', '9876543211', 'North Campus', SYSDATE);
INSERT INTO hostels VALUES (seq_hostels.NEXTVAL, 'International Hostel', 30, 'Dr. Khan', '9876543212', 'International Block', SYSDATE);

COMMIT;
