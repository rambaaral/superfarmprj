$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
if (Test-Path -LiteralPath '.env') { Write-Host '.env exists; existing account settings are retained.'; exit 0 }
$adminName = Read-Host 'New administrator username (default: local_admin)'
if (-not $adminName) { $adminName = 'local_admin' }
if ($adminName -notmatch '^[a-zA-Z0-9_-]{3,40}$') { throw 'Use 3-40 letters, digits, underscores or hyphens.' }
$adminSecret = Read-Host 'New administrator password (15+ characters)' -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($adminSecret)
try { $adminPass = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }
if ($adminPass.Length -lt 15 -or $adminPass.Contains("'") -or $adminPass.Contains("`n") -or $adminPass.Contains("`r")) { throw 'Use 15+ characters; single quotes and newlines are not supported by this setup helper.' }
function New-RandomSecret { $b = New-Object byte[] 32; $rng = [Security.Cryptography.RandomNumberGenerator]::Create(); try { $rng.GetBytes($b) } finally { $rng.Dispose() }; return ([BitConverter]::ToString($b)).Replace('-','').ToLowerInvariant() }
$lines = @('WEB_PORT=8080','MAIL_PORT=8025',"ADMIN_USER=$adminName",'ADMIN_EMAIL=admin@example.test',"ADMIN_PASSWORD='$adminPass'",('DB_PASSWORD='+(New-RandomSecret)),('DB_ROOT_PASSWORD='+(New-RandomSecret)))
[IO.File]::WriteAllLines((Join-Path $PSScriptRoot '.env'),$lines,(New-Object Text.UTF8Encoding($false)))
Write-Host 'Created local configuration. Run: docker compose up -d --build'
