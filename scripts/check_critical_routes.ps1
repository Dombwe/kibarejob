param(
    [string]$BackendPath = ".\backend"
)

$ErrorActionPreference = "Stop"

$routes = @(
    "home",
    "candidate_landing",
    "public_jobs",
    "faq",
    "contact",
    "privacy_policy",
    "terms_of_use",
    "app_login",
    "recruiter_dashboard",
    "recruiter_offers",
    "recruiter_applications",
    "admin",
    "admin_job_offer_index",
    "admin_job_import_source_index"
)

$resolvedBackend = Resolve-Path -LiteralPath $BackendPath
Push-Location $resolvedBackend

try {
    $failed = @()

    foreach ($route in $routes) {
        $output = & php bin/console debug:router $route 2>&1
        if ($LASTEXITCODE -ne 0) {
            $failed += $route
            Write-Host "[KO] $route" -ForegroundColor Red
            Write-Host $output
        } else {
            Write-Host "[OK] $route" -ForegroundColor Green
        }
    }

    if ($failed.Count -gt 0) {
        throw "Routes critiques manquantes: $($failed -join ', ')"
    }

    Write-Host "Toutes les routes critiques sont déclarées." -ForegroundColor Green
} finally {
    Pop-Location
}
