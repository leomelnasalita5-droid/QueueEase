
CREATE DATABASE IF NOT EXISTS queueease;
USE queueease;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE queues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    queue_number INT NOT NULL,
    queue_date DATE NOT NULL,
    status ENUM('Waiting', 'Serving', 'Completed', 'Cancelled')
        DEFAULT 'Waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    UNIQUE (service_id, queue_date, queue_number)
);

CREATE TABLE queue_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    queue_id INT NOT NULL,
    status VARCHAR(30) NOT NULL,
    changed_by INT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_id) REFERENCES queues(id),
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

INSERT INTO services (name, description) VALUES
('Registrar', 'Student records and documents'),
('Accounting', 'Tuition and payment concerns'),
('Guidance Office', 'Student counseling and assistance'),
('IT Office', 'Technical support'),
('Student Affairs', 'Student services and activities');
