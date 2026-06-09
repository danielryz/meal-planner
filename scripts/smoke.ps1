# Smoke tests against a running MealPlanner instance.
# Usage: $env:BASE_URL = "http://localhost:8080"; .\scripts\smoke.ps1

param(
    [string]$BaseUrl = $env:BASE_URL ?? "http://localhost:8080"
)

$pass    = 0
$fail    = 0
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

function Check {
    param([string]$Label, [int]$Expected, [int]$Actual)
    if ($Actual -eq $Expected) {
        Write-Host "  PASS  $Label" -ForegroundColor Green
        $script:pass++
    } else {
        Write-Host "  FAIL  $Label  (expected HTTP $Expected, got $Actual)" -ForegroundColor Red
        $script:fail++
    }
}

function Get-StatusCode {
    param([string]$Url, [string]$Method = "GET", $Body = $null, [hashtable]$Headers = @{})
    try {
        $params = @{
            Uri             = $Url
            Method          = $Method
            WebSession      = $session
            UseBasicParsing = $true
            ErrorAction     = "Stop"
        }
        if ($null -ne $Body) { $params["Body"] = $Body }
        if ($Headers.Count -gt 0) { $params["Headers"] = $Headers }

        $resp = Invoke-WebRequest @params
        return $resp.StatusCode
    } catch [System.Net.WebException] {
        $code = [int]$_.Exception.Response.StatusCode
        return if ($code -ne 0) { $code } else { 0 }
    } catch {
        return 0
    }
}

Write-Host ""
Write-Host "MealPlanner smoke tests -> $BaseUrl"
Write-Host "────────────────────────────────────"

# --- Public pages ---
Write-Host ""
Write-Host "Public pages"
Check "Login page loads"     200 (Get-StatusCode "$BaseUrl/login")
Check "Register page loads"  200 (Get-StatusCode "$BaseUrl/register")
Check "Unknown route is 404" 404 (Get-StatusCode "$BaseUrl/this-does-not-exist")

# --- Unauthenticated API ---
Write-Host ""
Write-Host "API without auth (expect 302)"
Check "GET /api/recipes"        302 (Get-StatusCode "$BaseUrl/api/recipes")
Check "GET /api/my-recipes"     302 (Get-StatusCode "$BaseUrl/api/my-recipes")
Check "GET /api/meal-plans"     302 (Get-StatusCode "$BaseUrl/api/meal-plans")
Check "GET /api/grocery-lists"  302 (Get-StatusCode "$BaseUrl/api/grocery-lists")
Check "GET /api/recipe-reviews" 302 (Get-StatusCode "$BaseUrl/api/recipe-reviews")

# --- Login ---
Write-Host ""
Write-Host "Authentication"

$loginCode = Get-StatusCode "$BaseUrl/login" -Method POST `
    -Body "email=employee@example.com&password=password"

Check "Login with valid credentials" 302 $loginCode

if ($loginCode -ne 302) {
    Write-Host ""
    Write-Host "  Skipping authenticated tests (login failed)" -ForegroundColor Yellow
} else {
    Write-Host ""
    Write-Host "Authenticated API"

    Check "GET /api/recipes returns 200"       200 (Get-StatusCode "$BaseUrl/api/recipes")
    Check "GET /api/my-recipes returns 200"    200 (Get-StatusCode "$BaseUrl/api/my-recipes")
    Check "GET /api/grocery-lists returns 200" 200 (Get-StatusCode "$BaseUrl/api/grocery-lists")
    Check "GET /api/profile returns 200"       200 (Get-StatusCode "$BaseUrl/api/profile")

    Check "GET /api/recipes/999999 returns 404" 404 (Get-StatusCode "$BaseUrl/api/recipes/999999")

    $draftBody = '{"title":"Testowy przepis dymny","description":"Opis testowy minimalny do testu smoke 20 znakow","ingredients":[{"name":"Makaron","amount":"200g"}],"steps":[{"instruction":"Ugotuj makaron"}]}'
    $draftCode = Get-StatusCode "$BaseUrl/api/recipes/drafts" -Method POST `
        -Body $draftBody -Headers @{ "Content-Type" = "application/json" }
    Check "POST /api/recipes/drafts returns 201" 201 $draftCode

    Check "GET /api/recipe-reviews returns 200" 200 (Get-StatusCode "$BaseUrl/api/recipe-reviews")
    Check "GET /api/meal-plans returns 200"     200 (Get-StatusCode "$BaseUrl/api/meal-plans")
}

# --- Summary ---
Write-Host ""
Write-Host "────────────────────────────────────"
$total = $pass + $fail
Write-Host "  Passed: $pass / $total"

if ($fail -gt 0) {
    Write-Host "  Failed: $fail" -ForegroundColor Red
    exit 1
} else {
    Write-Host "  All tests passed." -ForegroundColor Green
    exit 0
}
