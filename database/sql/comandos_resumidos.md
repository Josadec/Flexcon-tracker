Los 3 pasos (PowerShell, desde la raíz del proyecto) JOS'S Computer
1. Respaldo (obligatorio, es irreversible):


& "d:\xampp\mysql\bin\mysqldump.exe" -u root "flexcon_db" > backup_antes_de_reset.sql

2. Limpiar los datos:


Get-Content c:\xampp\htdocs\flexcon-tracker\database\sql\reset_corrida.sql | & "c:\xampp\mysql\bin\mysql.exe" -u root "flexcon_db"

3. Limpiar los PDFs de PO en storage:


powershell -ExecutionPolicy Bypass -File c:\xampp\htdocs\flexcon-tracker\database\sql\reset_storage.ps1
