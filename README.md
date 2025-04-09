# 📁📤 FileUploader 📤📁

The `FileUploader` class is a PHP utility that helps manage file uploads and update your database with less hassle. It's reusable, consistent, and reduces the need to write the same file upload logic repeatedly. ✨🛠️🧩

## 🎯 Motivation 🎯

I built this class because I got tired of writing the same upload code over and over again for different projects.  I also couldn't find any online solution that matched what I needed. This class wraps all the essential parts of handling file uploads—validation, creating directories, and updating databases—into a single package that’s easy to use. 🔁📦

## 📝 Note 📝

All examples in this documentation use a profile picture upload scenario, but the class can be adapted for other file upload situations too.

Assumed database schema:

```sql
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  profile_picture VARCHAR(255) DEFAULT NULL,
  -- other columns...
);
```

## 🔄 Execution Flow 🔄

The `FileUploader` processes file uploads step by step:

### 1️⃣ Class Instantiation

When you create a new `FileUploader` object:

- **PDO connection** is saved for DB actions.
- **Upload directory** is set (relative to `assets/`).
- **Allowed file extensions** are defined.
- **Database table & column names** are stored.
- **Max file size** is set (default: 8MB). 🏗️📦⚙️

### 2️⃣ File Upload Process

When calling `uploadPicture()`:

#### 📥 Parameter Processing

- Uses `$_FILES['file']` input.
- Captures user ID.
- Optionally sets a filename prefix (uses ID if not set).

#### ✅ Validation Checks

- Makes sure a file is uploaded.
- Checks for PHP errors.
- Confirms file size is within limits.
- Verifies file extension.
- Validates MIME type.
- Validates MetaData
- Performs extra checks for some file types.

#### 📁 Directory Management

- Checks for upload folder.
- Creates folder if it doesn’t exist.
- Applies correct permissions.

#### 🔧 File Processing

- Builds a unique filename.
- Adds original file extension.
- Moves file to target directory.

#### 🗃️ Database Update

- Runs SQL to update the table.
- Sets filename in the correct column.
- Uses the ID column and value to find the record.

#### ✅ Completion

- Returns new filename on success.
- Throws detailed exceptions if any step fails. 🚫

## 🌟 Features 🌟

- Validates file size, type, MIME and MetaData.
- Supports many file types (images, docs, audio, archives).
- Auto-creates directories and sets permissions.
- Updates database with the uploaded file name.
- Gives clear error messages.
- Throws exceptions for issues like:
  - Invalid file type
  - File too large
  - Upload error
  - DB update failure
  - Directory creation failure
  - File move failure
  - Invalid format for certain files

## 🛠️ Usage 🛠️

### 🔧 Constructor Parameters

You initialize `FileUploader` with:

- **PDO \$conn**: Database connection.
- **string \$uploadsDir**: Folder to store uploads (`assets/` relative).
- **array \$allowedExtensions**: Allowed file extensions.
- **string \$tableName**: Table to update.
- **string \$columnName**: Column to hold file name.
- **string \$identifierColumn**: Column to identify the record.
- **int \$maxFileSize**: Max file size in MB (default 8MB).

### ⬆️ Upload Method

Use `uploadPicture()` with:

- **array \$file**: `$_FILES` input.
- **string \$identifierValue**: Record ID (e.g., user ID).
- **string \$prefix**: Optional filename prefix.

### 📄 Supported File Types

**Images**: jpg, jpeg, png, gif, bmp, webp, ico, jfif, svg\
**Documents**: pdf, txt, docx, xlsx, pptx\
**Audio**: mp3, mp4, wav\
**Archives**: zip, rar, 7z (rar and 7z need extra PHP extensions)

### 💡 Example

```php
require 'fileUploader.php';

$conn = new PDO('mysql:host=localhost;dbname=test', 'username', 'password');

$fileUploader = new FileUploader(
  $conn,
  'profile_uploads',
  ['jpg', 'png', 'pdf'],
  'users',
  'profile_picture',
  'id',
  8 // Max size in MB
);

try {
  // $userId is the ID of the user
  $uploadedFileName = $fileUploader->uploadPicture($_FILES['file'], $userId, 'user_avatar_');
  echo "File uploaded successfully: " . $uploadedFileName;
} catch (Exception $e) {
  echo "Error: " . $e->getMessage();
}
```

## ⚙️ Requirements ⚙️

- PHP 7.4 or newer
- PDO extension
- fileinfo extension (for MIME checks)
- ZipArchive extension (for ZIP/Office file validation)
- MySQL-compatible database
- Write access to upload directory

Optional for more file types:

- RarArchive extension (for .rar)
- SevenZip extension (for .7z)

## 🤝 Contributing 🤝

Contributions are welcome! If you have ideas or feature requests, feel free to open an issue or send a pull request. Make sure your code matches the existing style and includes proper tests.

## 🐞 Reporting Issues 🐞

If something goes wrong, please share detailed steps to reproduce the issue and any error messages you received. 🔍

## 📄 License 📄

This project uses the MIT License. See the [LICENSE](./LICENSE) file for full details. 📜
