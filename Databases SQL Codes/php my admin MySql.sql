CREATE DATABASE IF NOT EXISTS courses_db;
USE courses_db;

CREATE TABLE IF NOT EXISTS courses (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(20)  NOT NULL,
    name          VARCHAR(100) NOT NULL,
    instructor_id INT          NOT NULL
);

CREATE TABLE IF NOT EXISTS enrollments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id  INT NOT NULL,
    UNIQUE KEY unique_enrollment (student_id, course_id)
);

INSERT INTO courses (code, name, instructor_id) VALUES
    ('CS101',   'Intro to Computer Science', 2),
    ('MATH201', 'Calculus I',                2),
    ('ENG301',  'Technical Writing',         2);
