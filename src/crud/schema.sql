CREATE DATABASE school_db;
USE school_db;

CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    course VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE loans(
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DOUBLE NOT NULL,
    loan_type VARCHAR(100) NOT NULL,
    status VARCHAR(100) NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students (id)
);