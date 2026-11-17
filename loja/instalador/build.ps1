# Compila o instalador do PC do balcao (C# 5, .NET Framework 4.x, sem NuGet).
$ErrorActionPreference = 'Stop'
$aqui = Split-Path -Parent $MyInvocation.MyCommand.Path
$csc = 'C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe'
$destino = Join-Path $aqui '..\..\public\downloads'
New-Item -ItemType Directory -Force -Path $destino | Out-Null
$saida = Join-Path (Resolve-Path $destino) 'NanaComanda-Instalador.exe'
$icone = Resolve-Path (Join-Path $aqui '..\..\public\favicon.ico')
& $csc /nologo /target:winexe /optimize+ /codepage:65001 "/out:$saida" "/win32manifest:$aqui\app.manifest" "/win32icon:$icone" `
    /r:System.dll /r:System.Windows.Forms.dll /r:System.Drawing.dll /r:System.Management.dll "$aqui\Instalador.cs"
if ($LASTEXITCODE -ne 0) { throw 'Falha ao compilar.' }
Write-Host "OK: $saida"
