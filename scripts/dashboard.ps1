[CmdletBinding()]
param(
    [ValidateSet('start', 'stop', 'restart', 'status', 'logs')]
    [string] $Action = 'start'
)

$dashboardDockerDirectory = Join-Path $env:LOCALAPPDATA 'Programs\DockerDesktop\resources\bin'
$dashboardDockerExecutable = Join-Path $dashboardDockerDirectory 'docker.exe'

if (-not (Test-Path -LiteralPath $dashboardDockerExecutable)) {
    $dashboardDockerCommand = Get-Command docker.exe -ErrorAction SilentlyContinue
    if ($null -eq $dashboardDockerCommand) {
        throw 'Docker CLI was not found. Start or install Docker Desktop first.'
    }
    $dashboardDockerExecutable = $dashboardDockerCommand.Source
    $dashboardDockerDirectory = Split-Path -Parent $dashboardDockerExecutable
}

# Docker invokes its credential helper by name, so its own directory must be
# available to this PowerShell process while it runs.
$env:Path = $dashboardDockerDirectory + ';' + $env:Path
$dashboardProjectDirectory = Split-Path -Parent $PSScriptRoot

Push-Location $dashboardProjectDirectory
try {
    switch ($Action) {
        'start' {
            & $dashboardDockerExecutable compose up --build -d
        }
        'stop' {
            & $dashboardDockerExecutable compose down
        }
        'restart' {
            & $dashboardDockerExecutable compose down
            if ($LASTEXITCODE -eq 0) {
                & $dashboardDockerExecutable compose up --build -d
            }
        }
        'status' {
            & $dashboardDockerExecutable compose ps
        }
        'logs' {
            & $dashboardDockerExecutable compose logs --tail 100
        }
    }
    $dashboardExitCode = $LASTEXITCODE
}
finally {
    Pop-Location
}

exit $dashboardExitCode

