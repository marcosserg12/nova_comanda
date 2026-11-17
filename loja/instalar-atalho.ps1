#Requires -Version 5.1
<#
.SYNOPSIS
    Cria o atalho "Nana Comanda - Balcão + Cozinha" no PC do balcão.
.DESCRIPTION
    Mede os monitores e cria, na área de trabalho, um atalho que abre UMA janela do Chrome
    cobrindo o monitor do balcão (à esquerda) e o da cozinha (logo à direita dele).
    Pergunta se o sistema deve abrir sozinho quando o computador ligar.
    Uso: clique duas vezes em instalar-atalho.bat, na mesma pasta.
.PARAMETER Url
    Endereço da tela Balcão + Cozinha.
.PARAMETER AbrirAoLigar
    S ou N. Vazio: pergunta.
.PARAMETER Simular
    Só mostra o que seria feito, sem criar nada.
#>
param(
    [string]$Url = 'https://comanda.mswebsolutions.site/estacao',
    [ValidateSet('', 'S', 'N')]
    [string]$AbrirAoLigar = '',
    [switch]$NaoAbrir,
    [switch]$Simular
)

$ErrorActionPreference = 'Stop'
$Nome = 'Nana Comanda - Balcão + Cozinha'
$LarguraPadrao = 1366

Add-Type -TypeDefinition @'
using System;
using System.Collections.Generic;
using System.Runtime.InteropServices;

public static class NanaMonitores
{
    [StructLayout(LayoutKind.Sequential)]
    public struct RECT { public int Left, Top, Right, Bottom; }

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    struct MONITORINFOEX
    {
        public int cbSize;
        public RECT rcMonitor;
        public RECT rcWork;
        public uint dwFlags;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 32)] public string szDevice;
    }

    delegate bool MonitorEnumProc(IntPtr monitor, IntPtr hdc, IntPtr rect, IntPtr data);

    [DllImport("user32.dll")] static extern bool EnumDisplayMonitors(IntPtr hdc, IntPtr clip, MonitorEnumProc callback, IntPtr data);
    [DllImport("user32.dll", CharSet = CharSet.Unicode)] static extern bool GetMonitorInfo(IntPtr monitor, ref MONITORINFOEX info);
    [DllImport("user32.dll")] static extern IntPtr SetThreadDpiAwarenessContext(IntPtr context);
    [DllImport("user32.dll")] static extern uint GetDpiForWindow(IntPtr window);
    [DllImport("dwmapi.dll")] static extern int DwmGetWindowAttribute(IntPtr window, int attribute, out RECT value, int size);

    public class Monitor
    {
        public string Device;
        public bool Primary;
        public RECT Bounds;
        public RECT Work;
    }

    // contexto -1: coordenadas lógicas (as que o Chrome usa); -4: pixels reais da tela.
    public static List<Monitor> Read(int context)
    {
        var list = new List<Monitor>();
        IntPtr previous = IntPtr.Zero;
        try { previous = SetThreadDpiAwarenessContext(new IntPtr(context)); }
        catch (EntryPointNotFoundException) { /* Windows antigo: uma escala só */ }
        try
        {
            EnumDisplayMonitors(IntPtr.Zero, IntPtr.Zero, (handle, hdc, rect, data) =>
            {
                var info = new MONITORINFOEX();
                info.cbSize = Marshal.SizeOf(typeof(MONITORINFOEX));
                if (GetMonitorInfo(handle, ref info))
                {
                    list.Add(new Monitor { Device = info.szDevice, Primary = (info.dwFlags & 1) != 0, Bounds = info.rcMonitor, Work = info.rcWork });
                }
                return true;
            }, IntPtr.Zero);
        }
        finally
        {
            if (previous != IntPtr.Zero) { SetThreadDpiAwarenessContext(previous); }
        }
        return list;
    }

    // Parte visível da janela (sem a borda invisível do Windows), em pixels reais, e o DPI dela.
    public static int[] VisibleWindow(IntPtr window)
    {
        IntPtr previous = IntPtr.Zero;
        try { previous = SetThreadDpiAwarenessContext(new IntPtr(-4)); }
        catch (EntryPointNotFoundException) { }
        try
        {
            RECT r;
            DwmGetWindowAttribute(window, 9, out r, Marshal.SizeOf(typeof(RECT)));
            uint dpi = 96;
            try { dpi = GetDpiForWindow(window); } catch (EntryPointNotFoundException) { }
            return new int[] { r.Left, r.Top, r.Right, r.Bottom, (int)dpi };
        }
        finally
        {
            if (previous != IntPtr.Zero) { SetThreadDpiAwarenessContext(previous); }
        }
    }
}
'@

function Get-Monitores {
    $logicos = [NanaMonitores]::Read(-1)
    $reais = [NanaMonitores]::Read(-4)
    foreach ($l in $logicos) {
        $r = $reais | Where-Object { $_.Device -eq $l.Device } | Select-Object -First 1
        if ($null -eq $r) { $r = $l }
        [pscustomobject]@{
            Nome    = $l.Device
            Primary = $l.Primary
            L       = $l
            R       = $r
            Largura = $l.Bounds.Right - $l.Bounds.Left
            Escala  = ($r.Bounds.Right - $r.Bounds.Left) / [double]($l.Bounds.Right - $l.Bounds.Left)
        }
    }
}

# Procura dois monitores lado a lado: o balcão (A) e a cozinha (B), colada à direita dele.
function Find-Par($monitores) {
    $ordenados = @($monitores | Sort-Object { $_.R.Bounds.Left })
    foreach ($a in $ordenados) {
        $melhor = $null
        $melhorAltura = 0
        foreach ($b in $ordenados) {
            if ($b -eq $a) { continue }
            if ([math]::Abs($b.R.Bounds.Left - $a.R.Bounds.Right) -gt 2) { continue }
            $altura = [math]::Min($a.R.Work.Bottom, $b.R.Work.Bottom) - [math]::Max($a.R.Work.Top, $b.R.Work.Top)
            if ($altura -gt 300 -and $altura -gt $melhorAltura) {
                $melhor = $b
                $melhorAltura = $altura
            }
        }
        if ($null -ne $melhor) { return , @($a, $melhor) }
    }
    return $null
}

function Find-Navegador {
    $caminhos = @(
        (Join-Path $env:ProgramFiles 'Google\Chrome\Application\chrome.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Google\Chrome\Application\chrome.exe'),
        (Join-Path $env:LOCALAPPDATA 'Google\Chrome\Application\chrome.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Microsoft\Edge\Application\msedge.exe'),
        (Join-Path $env:ProgramFiles 'Microsoft\Edge\Application\msedge.exe')
    )
    foreach ($caminho in $caminhos) {
        if ($caminho -and (Test-Path $caminho)) { return $caminho }
    }
    return $null
}

Write-Host ''
Write-Host '=== Nana Comanda: atalho do PC do balcão ===' -ForegroundColor Yellow
Write-Host ''

$navegador = Find-Navegador
if ($null -eq $navegador) {
    Write-Host 'Não encontrei o Google Chrome neste computador. Instale o Chrome e rode de novo.' -ForegroundColor Red
    exit 1
}

$monitores = @(Get-Monitores)
$par = Find-Par $monitores
$avisos = @()

if ($null -ne $par) {
    $a = $par[0]
    $b = $par[1]

    # Retângulo em pixels reais: do balcão até o fim da cozinha, na altura que cabe nos dois.
    $esquerda = $a.R.Work.Left
    $direita = $b.R.Work.Right
    $topo = [math]::Max($a.R.Work.Top, $b.R.Work.Top)
    $base = [math]::Min($a.R.Work.Bottom, $b.R.Work.Bottom)

    if ([math]::Abs($a.Escala - $b.Escala) -gt 0.01) {
        # Escalas diferentes: a janela precisa ficar com a escala do balcão, então a parte
        # do balcão tem de ser um pouco maior que a da cozinha.
        $parteBalcao = $a.R.Work.Right - $esquerda
        if (($direita - $b.R.Work.Left) -ge $parteBalcao) {
            $direita = $b.R.Work.Left + $parteBalcao - 8
        }
        $avisos += ('Os monitores estão com escalas diferentes ({0:P0} e {1:P0}). A cozinha vai aparecer com letras menores ou maiores que o normal.' -f $a.Escala, $b.Escala)
    }

    # O Chrome posiciona a janela na escala do monitor do balcão.
    $x = [int][math]::Round($a.L.Bounds.Left + ($esquerda - $a.R.Bounds.Left) / $a.Escala)
    $y = [int][math]::Round($a.L.Bounds.Top + ($topo - $a.R.Bounds.Top) / $a.Escala)
    $largura = [int][math]::Round(($direita - $esquerda) / $a.Escala)
    $altura = [int][math]::Round(($base - $topo) / $a.Escala)

    $alvo = @($esquerda, $topo, $direita, $base)
    Write-Host ("Monitor do balcão:  {0} de largura" -f $a.Largura)
    Write-Host ("Monitor da cozinha: {0} de largura, à direita do balcão" -f $b.Largura)
    $larguraBalcao = $a.Largura
} else {
    $unico = $monitores | Where-Object { $_.Primary } | Select-Object -First 1
    if ($null -eq $unico) { $unico = $monitores[0] }
    $x = $unico.L.Work.Left
    $y = $unico.L.Work.Top
    $largura = $unico.L.Work.Right - $unico.L.Work.Left
    $altura = $unico.L.Work.Bottom - $unico.L.Work.Top
    $larguraBalcao = $unico.Largura
    $alvo = @($unico.R.Work.Left, $unico.R.Work.Top, $unico.R.Work.Right, $unico.R.Work.Bottom)
    Write-Host 'Encontrei só um monitor (ou os monitores não estão lado a lado).' -ForegroundColor Red
    $avisos += 'A tela vai mostrar só o balcão. Para usar o 2º monitor: Configurações > Sistema > Tela > "Estender estas telas", com a cozinha à DIREITA do balcão. Depois rode este instalador de novo.'
}

$perfil = Join-Path $env:LOCALAPPDATA 'NanaComanda\estacao'
function Get-Argumentos {
    '--user-data-dir="{0}" --app="{1}" --window-position={2},{3} --window-size={4},{5}' -f $perfil, $Url, $x, $y, $largura, $altura
}
$argumentos = Get-Argumentos
$areaDeTrabalho = [Environment]::GetFolderPath('Desktop')
$atalho = Join-Path $areaDeTrabalho "$Nome.lnk"
$inicializar = Join-Path ([Environment]::GetFolderPath('Startup')) "$Nome.lnk"

Write-Host ("Janela: posição {0},{1}, tamanho {2}x{3}" -f $x, $y, $largura, $altura)

if ($Simular) {
    Write-Host ''
    Write-Host "[simulação] Navegador: $navegador"
    Write-Host "[simulação] Argumentos: $argumentos"
    Write-Host "[simulação] Atalho: $atalho"
    foreach ($aviso in $avisos) { Write-Host "Aviso: $aviso" -ForegroundColor Yellow }
    exit 0
}

function Save-Atalho {
    $shell = New-Object -ComObject WScript.Shell
    $lnk = $shell.CreateShortcut($atalho)
    $lnk.TargetPath = $navegador
    $lnk.Arguments = Get-Argumentos
    $lnk.WorkingDirectory = Split-Path $navegador
    $lnk.IconLocation = "$navegador,0"
    $lnk.Description = 'Nana Comanda: balcão no monitor da esquerda, cozinha no da direita'
    $lnk.Save()
    if (Test-Path $inicializar) { Copy-Item -Force $atalho $inicializar }
}

# A janela do Chrome que usa o perfil do Nana Comanda.
function Get-Janela {
    $ids = @(Get-CimInstance Win32_Process -Filter "Name='chrome.exe' OR Name='msedge.exe'" |
        Where-Object { $_.CommandLine -like '*NanaComanda\estacao*' } | ForEach-Object { $_.ProcessId })
    if ($ids.Count -eq 0) { return $null }
    Get-Process -Id $ids -ErrorAction SilentlyContinue | Where-Object { $_.MainWindowHandle -ne [IntPtr]::Zero } | Select-Object -First 1
}

function Close-Janela {
    for ($i = 0; $i -lt 3; $i++) {
        $janela = Get-Janela
        if ($null -eq $janela) { return }
        [void]$janela.CloseMainWindow()
        [void]$janela.WaitForExit(8000)
    }
}

function Open-Janela {
    Start-Process -FilePath $atalho
    for ($i = 0; $i -lt 40; $i++) {
        Start-Sleep -Milliseconds 500
        if ($null -ne (Get-Janela)) {
            Start-Sleep -Milliseconds 1500
            return Get-Janela
        }
    }
    return $null
}

# Diferença, em pixels reais, entre a parte visível da janela e o espaço dos monitores.
function Get-Desvio($janela) {
    $v = [NanaMonitores]::VisibleWindow($janela.MainWindowHandle)
    [pscustomobject]@{
        Esquerda = $v[0] - $alvo[0]
        Topo     = $v[1] - $alvo[1]
        Direita  = $v[2] - $alvo[2]
        Base     = $v[3] - $alvo[3]
        Escala   = $v[4] / 96.0
    }
}

function Test-Certo($d) {
    ([math]::Abs($d.Esquerda) -le 2) -and ([math]::Abs($d.Topo) -le 2) -and ([math]::Abs($d.Direita) -le 2) -and ([math]::Abs($d.Base) -le 2)
}

New-Item -ItemType Directory -Force -Path $perfil | Out-Null
Save-Atalho
Write-Host ''
Write-Host "Atalho criado na área de trabalho: $Nome" -ForegroundColor Green

while ($AbrirAoLigar -notin @('S', 'N')) {
    $AbrirAoLigar = (Read-Host 'Abrir sozinho quando o computador ligar? (S/N)').Trim().ToUpper()
}
if ($AbrirAoLigar -eq 'S') {
    Copy-Item -Force $atalho $inicializar
    Write-Host 'Pronto: o sistema vai abrir sozinho quando o computador ligar.' -ForegroundColor Green
} elseif (Test-Path $inicializar) {
    Remove-Item -Force $inicializar
    Write-Host 'O sistema não vai mais abrir sozinho ao ligar.'
}

foreach ($aviso in $avisos) {
    Write-Host ''
    Write-Host "Aviso: $aviso" -ForegroundColor Yellow
}

Write-Host ''
Write-Host 'Próximos passos:' -ForegroundColor Yellow
Write-Host '  1. Na janela que abrir, entre com o usuário do balcão e marque "Manter conectado neste aparelho".'
if ($larguraBalcao -ne $LarguraPadrao) {
    Write-Host ("  2. O monitor do balcão tem {0} de largura (o sistema espera {1})." -f $larguraBalcao, $LarguraPadrao)
    Write-Host '     Clique uma vez em "Detectar monitores", no topo, e depois em "Permitir".'
} else {
    Write-Host '  2. Se a divisão entre balcão e cozinha não cair na borda dos monitores,'
    Write-Host '     clique uma vez em "Detectar monitores", no topo, e depois em "Permitir".'
}

if ($NaoAbrir) { exit 0 }

Write-Host ''
if ($null -ne (Get-Janela)) {
    Write-Host 'Fechando a janela do Nana Comanda que estava aberta, para conferir a posição...'
    Close-Janela
}
Write-Host 'Abrindo e conferindo a posição da janela...'
$janela = Open-Janela
if ($null -eq $janela) {
    Write-Host 'A janela não abriu a tempo para conferir. Abra o atalho e veja se cobre os monitores.' -ForegroundColor Yellow
    exit 0
}
$desvio = Get-Desvio $janela
if (-not (Test-Certo $desvio)) {
    # O Windows soma bordas invisíveis à janela; corrige uma vez e confere de novo.
    $e = $desvio.Escala
    $x = $x - [int][math]::Round($desvio.Esquerda / $e)
    $y = $y - [int][math]::Round($desvio.Topo / $e)
    $largura = $largura - [int][math]::Round(($desvio.Direita - $desvio.Esquerda) / $e)
    $altura = $altura - [int][math]::Round(($desvio.Base - $desvio.Topo) / $e)
    Save-Atalho
    Close-Janela
    $janela = Open-Janela
    if ($null -ne $janela) { $desvio = Get-Desvio $janela }
}
if ($null -ne $janela -and (Test-Certo $desvio)) {
    Write-Host 'Janela conferida: cobre os monitores certinho.' -ForegroundColor Green
} else {
    Write-Host ('A janela ficou um pouco fora do lugar (esquerda {0}, topo {1}, direita {2}, base {3} px). Ela funciona, mas pode sobrar uma faixa na borda.' -f $desvio.Esquerda, $desvio.Topo, $desvio.Direita, $desvio.Base) -ForegroundColor Yellow
}
