<?php
// config.php - تكوين قاعدة البيانات (مصحح)

// إعداد قاعدة البيانات والاتصال
$db = new PDO('sqlite:data.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// تنفيذ إنشاء الجداول
$db->exec("
CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE,
  password TEXT
);

CREATE TABLE IF NOT EXISTS tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  token TEXT UNIQUE,
  description TEXT,
  is_active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT UNIQUE,
  position INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS channels (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT,
  url TEXT,
  category_id INTEGER,
  logo TEXT,
  enabled INTEGER DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(category_id) REFERENCES categories(id)
);

CREATE TABLE IF NOT EXISTS packages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT,
  position INTEGER DEFAULT 0,
  description TEXT
);

CREATE TABLE IF NOT EXISTS package_channels (
  package_id INTEGER,
  channel_id INTEGER,
  PRIMARY KEY (package_id, channel_id),
  FOREIGN KEY(package_id) REFERENCES packages(id),
  FOREIGN KEY(channel_id) REFERENCES channels(id)
);

CREATE TABLE IF NOT EXISTS subscribers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE,
  password TEXT,
  package_id INTEGER,
  token_id INTEGER,
  -- **تم إضافة عمود التوكن هنا ليتوافق مع m3u.php**
  token TEXT, 
  status TEXT DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(package_id) REFERENCES packages(id),
  FOREIGN KEY(token_id) REFERENCES tokens(id)
);

CREATE TABLE IF NOT EXISTS logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  action TEXT,
  data TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// إنشاء المسؤول الافتراضي والتوكن
$pw = password_hash('admin123', PASSWORD_DEFAULT);
$db->exec("INSERT OR IGNORE INTO admins (id, username, password) VALUES (1,'admin','$pw')");
$db->exec("INSERT OR IGNORE INTO tokens (token, description) VALUES ('test123','default token')");
?>
