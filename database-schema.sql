-- Database schema for Student Marking System

-- Create database
CREATE DATABASE IF NOT EXISTS marking_system;
USE marking_system;

-- Create Marker table
CREATE TABLE IF NOT EXISTS Marker (
    Email VARCHAR(255) PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    Password CHAR(64) NOT NULL,  -- SHA256 hash (64 characters)
    Administrator BOOLEAN DEFAULT FALSE,
    LastLogin DATETIME
);

-- Create Student table
CREATE TABLE IF NOT EXISTS Student (
    ID CHAR(8) PRIMARY KEY,
    FirstName VARCHAR(255) NOT NULL,
    LastName VARCHAR(255) NOT NULL,
    Email VARCHAR(255) NOT NULL UNIQUE,
    FirstMarker VARCHAR(255),
    SecondMarker VARCHAR(255),
    Programme VARCHAR(255) NOT NULL,
    FOREIGN KEY (FirstMarker) REFERENCES Marker(Email),
    FOREIGN KEY (SecondMarker) REFERENCES Marker(Email)
);

-- Create Coursework table
CREATE TABLE IF NOT EXISTS Coursework (
    ReportID INT AUTO_INCREMENT PRIMARY KEY,
    StudentID CHAR(8) NOT NULL,
    FirstMarker VARCHAR(255),
    SecondMarker VARCHAR(255),
    
    -- First Marker Assessment
    FM_ProjectDefinition DECIMAL(5,2),
    FM_ProjectDefinitionComments TEXT,
    FM_ContextReview DECIMAL(5,2),
    FM_ContextReviewComments TEXT,
    FM_Methodology DECIMAL(5,2),
    FM_MethodologyComments TEXT,
    FM_Evaluation DECIMAL(5,2),
    FM_EvaluationComments TEXT,
    FM_Structure DECIMAL(5,2),
    FM_StructureComments TEXT,
    FM_LastModified DATETIME,
    
    -- Second Marker Assessment
    SM_ProjectDefinition DECIMAL(5,2),
    SM_ProjectDefinitionComments TEXT,
    SM_ContextReview DECIMAL(5,2),
    SM_ContextReviewComments TEXT,
    SM_Methodology DECIMAL(5,2),
    SM_MethodologyComments TEXT,
    SM_Evaluation DECIMAL(5,2),
    SM_EvaluationComments TEXT,
    SM_Structure DECIMAL(5,2),
    SM_StructureComments TEXT,
    SM_LastModified DATETIME,
    
    FOREIGN KEY (StudentID) REFERENCES Student(ID),
    FOREIGN KEY (FirstMarker) REFERENCES Marker(Email),
    FOREIGN KEY (SecondMarker) REFERENCES Marker(Email)
);

-- Create Practice table
CREATE TABLE IF NOT EXISTS Practice (
    ReportID INT AUTO_INCREMENT PRIMARY KEY,
    StudentID CHAR(8) NOT NULL,
    FirstMarker VARCHAR(255),
    SecondMarker VARCHAR(255),
    
    -- First Marker Assessment
    FM_Communication DECIMAL(5,2),
    FM_CommunicationComments TEXT,
    FM_PosterStructure DECIMAL(5,2),
    FM_PosterStructureComments TEXT,
    FM_Interview DECIMAL(5,2),
    FM_InterviewComments TEXT,
    FM_LastModified DATETIME,
    
    -- Second Marker Assessment
    SM_Communication DECIMAL(5,2),
    SM_CommunicationComments TEXT,
    SM_PosterStructure DECIMAL(5,2),
    SM_PosterStructureComments TEXT,
    SM_Interview DECIMAL(5,2),
    SM_InterviewComments TEXT,
    SM_LastModified DATETIME,
    
    FOREIGN KEY (StudentID) REFERENCES Student(ID),
    FOREIGN KEY (FirstMarker) REFERENCES Marker(Email),
    FOREIGN KEY (SecondMarker) REFERENCES Marker(Email)
);

-- Add a sample admin user (password is SHA256 hash of "admin@example.com")
INSERT INTO Marker (Email, Name, Password, Administrator) VALUES 
('admin@example.com', 'Admin User', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', TRUE);
