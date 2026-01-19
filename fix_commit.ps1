# PowerShell script to fix the git commit with secret
# This will preserve ALL your commits and messages

Write-Host "Step 1: Setting up custom editor for rebase..." -ForegroundColor Cyan

# Create a temporary rebase script
$rebaseScript = @'
pick %COMMIT1%
pick %COMMIT2%
pick %COMMIT3%
'@

# Get commit hashes
$commits = git log --oneline -3 --format="%H"
$commitArray = $commits -split "`n"

Write-Host "Your commits:" -ForegroundColor Yellow
git log --oneline -3

Write-Host "`nStep 2: We need to edit the SECOND commit (f6e4ff2) which has the secret" -ForegroundColor Cyan
Write-Host "Press any key to start the interactive rebase..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

# Set a custom editor
$env:GIT_SEQUENCE_EDITOR = "notepad"

# Start rebase
git rebase -i HEAD~3
