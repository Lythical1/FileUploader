<?php

/**
 * FileUpload class handles file uploads and database updates
 * 
 * This class provides functionality to upload files, validate them, and update the database with the new filename.
 * It also creates necessary directories and sets permissions.
 * 
 * @package FileUpload
 * @version 1.0
 * @author Lythical
 * @license MIT
 */

class FileUploader
{
    private $uploadsDir;
    private $tableName;
    private $columnName;
    private $identifierColumn;
    private $conn;
    private $maxFileSize;
    private $allowedExtensions = [];
    private $extensionMimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
        'ico' => 'image/vnd.microsoft.icon',
        'jfif' => 'image/jpeg',


        'svg' => 'image/svg+xml',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',


        'pdf' => 'application/pdf',
        'txt' => 'text/plain',


        'mp3' => 'audio/mpeg',
        'mp4' => 'audio/mp4',
        'wav' => 'audio/wav',


        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed'
    ];


    /**
     * Constructor for the FileUpload class
     * 
     * Initializes the FileUpload class with the necessary parameters, validates inputs, and creates the required directories.
     * 
     * @param PDO $conn The PDO database connection
     * @param string $uploadsDir The directory to store uploaded files (relative to 'assets/')
     * @param array $allowedExtensions Array of allowed file extensions (e.g., ['jpg', 'png', 'pdf'])
     * @param string $tableName Database table name to update
     * @param string $columnName Column name that stores the file name (e.g., 'Picture')
     * @param string $identifierColumn Column name that uniquely identifies the record (e.g., 'ID')
     * @param int $maxFileSize Maximum file size in megabytes (default is 8MB)
     * @throws Exception If any of the parameters are invalid
     */
    public function __construct($conn, $uploadsDir, $allowedExtensions, $tableName, $columnName, $identifierColumn, $maxFileSize = 8)
    {
        switch (true) {
            case empty($conn):
                throw new Exception("Database connection cannot be empty");
            case empty($uploadsDir):
                throw new Exception("Upload directory cannot be empty");
            case empty($tableName):
                throw new Exception("Table name cannot be empty");
            case empty($columnName):
                throw new Exception("Column name cannot be empty");
            case empty($identifierColumn):
                throw new Exception("Identifier column cannot be empty");
            case !is_array($allowedExtensions):
                throw new Exception("Allowed extensions must be an array");
            case count($allowedExtensions) === 0:
                throw new Exception("Allowed extensions array cannot be empty");
        }

        $this->conn = $conn;
        $this->maxFileSize = $maxFileSize * 1024 * 1024;
        $this->allowedExtensions = $allowedExtensions;
        $this->uploadsDir = 'assets/' . $uploadsDir;
        $this->tableName = $tableName;
        $this->columnName = $columnName;
        $this->identifierColumn = $identifierColumn;

        $this->createDirectory();
    }

    private function createDirectory()
    {
        // Create directory if it doesn't exist
        if (!is_dir('assets')) {
            mkdir('assets', 0777, true);
        } else {
            try {
                $currentPerms = fileperms('assets') & 0777;
                if ($currentPerms != 0777) {
                    chmod('assets', 0777);
                }
            } catch (Exception $e) {
                throw new Exception("Error setting permissions for the directory.");
            }
        }
        // Create uploads directory if it doesn't exist
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0777, true);
        } else {
            try {
                $currentPerms = fileperms($this->uploadsDir) & 0777;
                if ($currentPerms != 0777) {
                    chmod($this->uploadsDir, 0777);
                }
            } catch (Exception $e) {
                throw new Exception("Error setting permissions for the directory.");
            }
        }
        return true;
    }

    /**
     * Upload a file and update the database
     * 
     * Handles the file upload process, including validation, renaming, moving the file to the target directory, and updating the database with the new file name.
     * 
     * @param array $file The $_FILES array element containing file upload information
     * @param string $identifierValue The value that identifies the exact record which the file should be associated with in the database (PDO::lastInsertId())
     * @param string $prefix Optional prefix for the filename (defaults to the identifier value if not provided)
     * @return string The name of the uploaded file
     * @throws Exception If the file upload or database update fails
     */

    public function uploadPicture($file, $identifierValue, $prefix = null)
    {
        // Set default prefix if none provided
        if ($prefix === null) {
            $prefix = $identifierValue;
        }

        // Validate file
        $this->validateFile($file);
    
        // Generate unique filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = uniqid($prefix . '_', true) . '.' . $extension;
        $targetPath = $this->uploadsDir . '/' . $fileName;
    
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Failed to upload file.");
        }
    
        // Update database
        $this->updateDatabase($fileName, $identifierValue);
    
        return $fileName;
    }

    /**
     * Validate uploaded file
     * 
     * Ensures the uploaded file meets the requirements, including size, extension, and MIME type. Also performs additional checks for image files.
     * 
     * @param array $file The $_FILES array element containing file upload information
     * @throws Exception If the file is invalid or does not meet the requirements
     */
    private function validateFile($file)
    {
        // Check if file was uploaded without errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "File exceeds the maximum upload size allowed by the server.",
            UPLOAD_ERR_FORM_SIZE => "File exceeds the maximum upload size allowed by the form.",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
            UPLOAD_ERR_NO_FILE => "No file was uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
            ];
            
            $errorMessage = isset($errorMessages[$file['error']]) 
            ? $errorMessages[$file['error']] 
            : "Unknown upload error.";
            
            throw new Exception($errorMessage);
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception("File is too large. Maximum size is " . ($this->maxFileSize / 1048576) . "MB.");
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new Exception("Invalid file type. Allowed types: " . implode(', ', $this->allowedExtensions));
        }
        
        // Validate MIME type
        $this->validateMimeType($file);

        // Validate metadata
        $this->validateMetaData($file);
    }

    /**
     * Validate MIME type of the uploaded file
     * 
     * Checks the MIME type of the uploaded file against the allowed MIME types based on the file extension.
     * 
     * @param array $file The $_FILES array element containing file upload information
     * @return bool True if the MIME type is valid
     * @throws Exception If the MIME type is invalid
     */
    
    private function validateMimeType($file)
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        // Build allowed MIME types dynamically from allowed extensions
        $allowedMimeTypes = [];
        foreach ($this->allowedExtensions as $ext) {
            if (isset($this->extensionMimeMap[$ext])) {
                $allowedMimeTypes[] = $this->extensionMimeMap[$ext];
            }
        }
        
        // Remove duplicates (e.g., jpg and jpeg both map to image/jpeg)
        $allowedMimeTypes = array_unique($allowedMimeTypes);
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new Exception("Invalid file type. File appears to be " . $mimeType);
        }
        
        return true;
    }

    /**
     * Validate metadata of the uploaded file
     * 
     * Checks the metadata of the uploaded file to ensure it is a valid image, PDF, or Office document.
     * 
     * @param array $file The $_FILES array element containing file upload information
     * @return bool True if the metadata is valid
     * @throws Exception If the metadata is invalid
     */

    private function validateMetaData($file)
    {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
        // Validate image metadata
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico', 'jfif'];
        if (in_array($extension, $imageExtensions)) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new Exception("Invalid image file. Could not read image dimensions.");
            }
        }
            
        // Validate PDF metadata
        if ($extension === 'pdf') {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle) {
                $header = fread($handle, 4);
                fclose($handle);
                if ($header !== '%PDF') {
                    throw new Exception("Invalid PDF file. Missing PDF header signature.");
                }
            }
        }
            
        // Validate Office documents
        $officeExtensions = ['docx', 'xlsx', 'pptx'];
        if (in_array($extension, $officeExtensions)) {
            $zip = new ZipArchive();
            $result = $zip->open($file['tmp_name']);
            if ($result !== true) {
                throw new Exception("Invalid Office document. File is not properly formatted.");
            }
            $zip->close();
        }
            
        // Validate archives
        if ($extension === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) !== true) {
                throw new Exception("Invalid ZIP archive.");
            }
            $zip->close();
        }

        // Validate audio files
        $audioExtensions = ['mp3', 'mp4', 'wav'];
        if (in_array($extension, $audioExtensions)) {
            $handle = fopen($file['tmp_name'], 'rb');
            if ($handle) {
                $header = fread($handle, 4);
                fclose($handle);
                if ($header !== 'RIFF' && $header !== 'ftyp') {
                    throw new Exception("Invalid audio file. Missing RIFF or ftyp header signature.");
                }
            }
        }
    /*

        // Uncomment the following lines if you want to validate RAR and 7z files. These need to be installed manually.


        if (class_exists('RarArchive')) {
        $rar = new RarArchive();
        if ($rar->open($file['tmp_name']) !== true) {
            throw new Exception("Invalid RAR archive.");
        }
        $rar->close();
        }
        
        if (class_exists('SevenZip')) {
        $sevenZip = new SevenZip();
        if (!$sevenZip->open($file['tmp_name'])) {
            throw new Exception("Invalid 7z archive.");
        }
        $sevenZip->close();
        }
        
    */
        return true;
    }
    

    /**
     * Update the database with the new filename
     * 
     * Updates the specified database table and column with the new file name for the record identified by the given identifier value.
     * 
     * @param string $fileName The name of the uploaded file
     * @param string $identifierValue The value that identifies the record in the database
     * @throws Exception If the database update fails
     */

    private function updateDatabase($fileName, $identifierValue)
    {
        $sql = "UPDATE {$this->tableName} SET {$this->columnName} = :fileName WHERE {$this->identifierColumn} = :identifierValue";
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bindParam(':fileName', $fileName);
        $stmt->bindParam(':identifierValue', $identifierValue);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update database with file information.");
        }
    }
}
?>
