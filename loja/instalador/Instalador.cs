// Nana Comanda - instalador do PC do balcao (C# 5, .NET Framework 4.x).
// Porta fiel de loja\instalar-atalho.ps1, com tela para o dono escolher os monitores.
using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Management;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Text;
using System.Threading;
using System.Windows.Forms;

namespace NanaInstalador
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
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 32)]
        public string szDevice;
    }

    delegate bool MonitorEnumProc(IntPtr monitor, IntPtr hdc, IntPtr rect, IntPtr data);

    static class Native
    {
        [DllImport("user32.dll")]
        public static extern bool EnumDisplayMonitors(IntPtr hdc, IntPtr clip, MonitorEnumProc callback, IntPtr data);
        [DllImport("user32.dll", CharSet = CharSet.Unicode)]
        public static extern bool GetMonitorInfo(IntPtr monitor, ref MONITORINFOEX info);
        [DllImport("user32.dll")]
        public static extern IntPtr SetThreadDpiAwarenessContext(IntPtr context);
        [DllImport("user32.dll")]
        public static extern uint GetDpiForWindow(IntPtr window);
        [DllImport("dwmapi.dll")]
        public static extern int DwmGetWindowAttribute(IntPtr window, int attribute, out RECT value, int size);
    }

    class RawMonitor
    {
        public string Device;
        public bool Primary;
        public RECT Bounds;
        public RECT Work;
    }

    // Um monitor, com as medidas logicas (L: o que o Chrome usa) e reais (R: pixels da tela).
    class Mon
    {
        public string Device;
        public bool Primary;
        public RawMonitor L;
        public RawMonitor R;
        public int LogicalWidth;
        public int PhysWidth;
        public int PhysHeight;
        public double Scale;
    }

    class Plan
    {
        public Mon A;      // balcao
        public Mon B;      // cozinha (pode ser null)
        public int X, Y, W, H;
        public int[] Target;   // esquerda, topo, direita, base (pixels reais)
        public List<string> Warnings = new List<string>();
        public string Url;
    }

    static class Geometry
    {
        // contexto -1: coordenadas logicas (as que o Chrome usa); -4: pixels reais da tela.
        public static List<RawMonitor> Read(int context)
        {
            List<RawMonitor> list = new List<RawMonitor>();
            IntPtr previous = IntPtr.Zero;
            try { previous = Native.SetThreadDpiAwarenessContext(new IntPtr(context)); }
            catch (EntryPointNotFoundException) { }
            try
            {
                MonitorEnumProc cb = delegate(IntPtr handle, IntPtr hdc, IntPtr rect, IntPtr data)
                {
                    MONITORINFOEX info = new MONITORINFOEX();
                    info.cbSize = Marshal.SizeOf(typeof(MONITORINFOEX));
                    if (Native.GetMonitorInfo(handle, ref info))
                    {
                        RawMonitor m = new RawMonitor();
                        m.Device = info.szDevice;
                        m.Primary = (info.dwFlags & 1) != 0;
                        m.Bounds = info.rcMonitor;
                        m.Work = info.rcWork;
                        list.Add(m);
                    }
                    return true;
                };
                Native.EnumDisplayMonitors(IntPtr.Zero, IntPtr.Zero, cb, IntPtr.Zero);
                GC.KeepAlive(cb);
            }
            finally
            {
                if (previous != IntPtr.Zero) { Native.SetThreadDpiAwarenessContext(previous); }
            }
            return list;
        }

        public static List<Mon> GetMonitors()
        {
            List<RawMonitor> logicos = Read(-1);
            List<RawMonitor> reais = Read(-4);
            List<Mon> result = new List<Mon>();
            foreach (RawMonitor l in logicos)
            {
                RawMonitor r = null;
                foreach (RawMonitor c in reais)
                {
                    if (c.Device == l.Device) { r = c; break; }
                }
                if (r == null) { r = l; }
                Mon m = new Mon();
                m.Device = l.Device;
                m.Primary = l.Primary;
                m.L = l;
                m.R = r;
                m.LogicalWidth = l.Bounds.Right - l.Bounds.Left;
                m.PhysWidth = r.Bounds.Right - r.Bounds.Left;
                m.PhysHeight = r.Bounds.Bottom - r.Bounds.Top;
                m.Scale = m.LogicalWidth > 0 ? (double)m.PhysWidth / (double)m.LogicalWidth : 1.0;
                result.Add(m);
            }
            return result;
        }

        static int Overlap(Mon a, Mon b)
        {
            return Math.Min(a.R.Work.Bottom, b.R.Work.Bottom) - Math.Max(a.R.Work.Top, b.R.Work.Top);
        }

        // Monitor colado a direita de a (+-2 px) com mais de 300 px de altura em comum.
        public static Mon FindRight(List<Mon> mons, Mon a)
        {
            Mon best = null;
            int bestH = 0;
            foreach (Mon b in mons)
            {
                if (object.ReferenceEquals(a, b)) { continue; }
                if (Math.Abs(b.R.Bounds.Left - a.R.Bounds.Right) > 2) { continue; }
                int h = Overlap(a, b);
                if (h > 300 && h > bestH) { best = b; bestH = h; }
            }
            return best;
        }

        // Procura o par balcao (A) + cozinha (B), na ordem da esquerda para a direita.
        public static Mon[] FindPair(List<Mon> mons)
        {
            List<Mon> ord = new List<Mon>(mons);
            ord.Sort(delegate(Mon p, Mon q) { return p.R.Bounds.Left.CompareTo(q.R.Bounds.Left); });
            foreach (Mon a in ord)
            {
                Mon b = FindRight(ord, a);
                if (b != null) { return new Mon[] { a, b }; }
            }
            return null;
        }

        public static string BuildUrl(string baseUrl, Mon a, Mon b)
        {
            string sep = baseUrl.IndexOf('?') >= 0 ? "&" : "?";
            string url = baseUrl + sep + "balcao=" + a.PhysWidth;
            if (b != null) { url += "&cozinha=" + b.PhysWidth; }
            return url;
        }

        public static Plan BuildPlan(List<Mon> mons, Mon a, Mon b, string baseUrl)
        {
            Plan p = new Plan();
            if (a != null && b != null)
            {
                p.A = a;
                p.B = b;
                int esquerda = a.R.Work.Left;
                int direita = b.R.Work.Right;
                int topo = Math.Max(a.R.Work.Top, b.R.Work.Top);
                int baseY = Math.Min(a.R.Work.Bottom, b.R.Work.Bottom);

                int desalinho = Math.Abs(a.R.Work.Top - b.R.Work.Top);
                if (desalinho > 8)
                {
                    p.Warnings.Add(string.Format("Os monitores estão desalinhados em {0} px: a janela vai usar só a parte em comum. Para aproveitar a tela toda, alinhe-os pelo topo nas configurações de tela e clique em Medir de novo.", desalinho));
                }
                if (Math.Abs(a.Scale - b.Scale) > 0.01)
                {
                    int parteBalcao = a.R.Work.Right - esquerda;
                    if ((direita - b.R.Work.Left) >= parteBalcao)
                    {
                        direita = b.R.Work.Left + parteBalcao - 8;
                    }
                    p.Warnings.Add(string.Format("Os monitores estão com escalas diferentes do Windows ({0}% e {1}%). A cozinha vai aparecer com letras menores ou maiores que o normal.", (int)Math.Round(a.Scale * 100), (int)Math.Round(b.Scale * 100)));
                }
                // O Chrome posiciona a janela na escala do monitor do balcao.
                p.X = (int)Math.Round(a.L.Bounds.Left + (esquerda - a.R.Bounds.Left) / a.Scale);
                p.Y = (int)Math.Round(a.L.Bounds.Top + (topo - a.R.Bounds.Top) / a.Scale);
                p.W = (int)Math.Round((direita - esquerda) / a.Scale);
                p.H = (int)Math.Round((baseY - topo) / a.Scale);
                p.Target = new int[] { esquerda, topo, direita, baseY };
            }
            else
            {
                Mon u = a;
                if (u == null)
                {
                    foreach (Mon m in mons) { if (m.Primary) { u = m; break; } }
                }
                if (u == null && mons.Count > 0) { u = mons[0]; }
                p.A = u;
                p.B = null;
                if (u != null)
                {
                    p.X = u.L.Work.Left;
                    p.Y = u.L.Work.Top;
                    p.W = u.L.Work.Right - u.L.Work.Left;
                    p.H = u.L.Work.Bottom - u.L.Work.Top;
                    p.Target = new int[] { u.R.Work.Left, u.R.Work.Top, u.R.Work.Right, u.R.Work.Bottom };
                }
                else
                {
                    p.Target = new int[] { 0, 0, 0, 0 };
                }
                if (mons.Count <= 1)
                {
                    p.Warnings.Add("Encontrei só um monitor. A tela vai mostrar só o balcão. Para usar o segundo monitor, abra as configurações de tela, escolha \"Estender estas telas\" e deixe a cozinha à DIREITA do balcão.");
                }
                else
                {
                    p.Warnings.Add("Os monitores não estão lado a lado: a cozinha precisa ficar à DIREITA do balcão, encostada nele. Ajuste nas configurações de tela e clique em Medir de novo.");
                }
            }
            if (p.A != null) { p.Url = BuildUrl(baseUrl, p.A, p.B); } else { p.Url = baseUrl; }
            return p;
        }
    }

    delegate void LogFn(string text, int kind); // kind: 0 normal, 1 verde, 2 amarelo, 3 vermelho, 4 titulo

    class Installer
    {
        public const string Nome = "Nana Comanda - Balcão + Cozinha";
        public const string DefaultUrl = "https://comanda.mswebsolutions.site/estacao";

        readonly LogFn log;
        string browser;
        string perfil;
        string atalho;
        string inicializar;
        int x, y, w, h;
        int[] alvo;
        string url;

        public Installer(LogFn log) { this.log = log; }

        static string FindBrowser()
        {
            string pf = Environment.GetEnvironmentVariable("ProgramFiles");
            string pf86 = Environment.GetEnvironmentVariable("ProgramFiles(x86)");
            string local = Environment.GetEnvironmentVariable("LOCALAPPDATA");
            List<string> paths = new List<string>();
            if (!string.IsNullOrEmpty(pf)) { paths.Add(Path.Combine(pf, @"Google\Chrome\Application\chrome.exe")); }
            if (!string.IsNullOrEmpty(pf86)) { paths.Add(Path.Combine(pf86, @"Google\Chrome\Application\chrome.exe")); }
            if (!string.IsNullOrEmpty(local)) { paths.Add(Path.Combine(local, @"Google\Chrome\Application\chrome.exe")); }
            if (!string.IsNullOrEmpty(pf86)) { paths.Add(Path.Combine(pf86, @"Microsoft\Edge\Application\msedge.exe")); }
            if (!string.IsNullOrEmpty(pf)) { paths.Add(Path.Combine(pf, @"Microsoft\Edge\Application\msedge.exe")); }
            foreach (string p in paths) { if (File.Exists(p)) { return p; } }
            return null;
        }

        string Argumentos()
        {
            return string.Format("--user-data-dir=\"{0}\" --app=\"{1}\" --window-position={2},{3} --window-size={4},{5}", perfil, url, x, y, w, h);
        }

        static void SetProp(object o, string name, object value)
        {
            o.GetType().InvokeMember(name, BindingFlags.SetProperty, null, o, new object[] { value });
        }

        void SaveShortcut()
        {
            Type t = Type.GetTypeFromProgID("WScript.Shell");
            if (t == null) { throw new InvalidOperationException("WScript.Shell não disponível."); }
            object shell = Activator.CreateInstance(t);
            object lnk = t.InvokeMember("CreateShortcut", BindingFlags.InvokeMethod, null, shell, new object[] { atalho });
            SetProp(lnk, "TargetPath", browser);
            SetProp(lnk, "Arguments", Argumentos());
            SetProp(lnk, "WorkingDirectory", Path.GetDirectoryName(browser));
            SetProp(lnk, "IconLocation", browser + ",0");
            SetProp(lnk, "Description", "Nana Comanda: balcão no monitor da esquerda, cozinha no da direita");
            lnk.GetType().InvokeMember("Save", BindingFlags.InvokeMethod, null, lnk, null);
            if (File.Exists(inicializar)) { File.Copy(atalho, inicializar, true); }
            Marshal.FinalReleaseComObject(lnk);
            Marshal.FinalReleaseComObject(shell);
        }

        // A janela do Chrome/Edge que usa o perfil do Nana Comanda (so esses processos).
        static Process FindWindow()
        {
            List<int> ids = new List<int>();
            using (ManagementObjectSearcher s = new ManagementObjectSearcher("SELECT ProcessId, CommandLine FROM Win32_Process WHERE Name='chrome.exe' OR Name='msedge.exe'"))
            using (ManagementObjectCollection col = s.Get())
            {
                foreach (ManagementObject mo in col)
                {
                    object cl = mo["CommandLine"];
                    if (cl != null && cl.ToString().IndexOf(@"NanaComanda\estacao", StringComparison.OrdinalIgnoreCase) >= 0)
                    {
                        ids.Add(Convert.ToInt32(mo["ProcessId"]));
                    }
                    mo.Dispose();
                }
            }
            foreach (int id in ids)
            {
                try
                {
                    Process p = Process.GetProcessById(id);
                    if (p.MainWindowHandle != IntPtr.Zero) { return p; }
                }
                catch (ArgumentException) { }
            }
            return null;
        }

        static void CloseWindow()
        {
            for (int i = 0; i < 3; i++)
            {
                Process p = FindWindow();
                if (p == null) { return; }
                try { p.CloseMainWindow(); p.WaitForExit(8000); }
                catch (InvalidOperationException) { }
            }
        }

        Process OpenWindow()
        {
            ProcessStartInfo psi = new ProcessStartInfo(atalho);
            psi.UseShellExecute = true;
            Process.Start(psi);
            for (int i = 0; i < 40; i++)
            {
                Thread.Sleep(500);
                if (FindWindow() != null)
                {
                    Thread.Sleep(1500);
                    return FindWindow();
                }
            }
            return null;
        }

        // Parte visivel da janela (sem a borda invisivel), em pixels reais, e o DPI dela.
        static int[] VisibleWindow(IntPtr window)
        {
            IntPtr previous = IntPtr.Zero;
            try { previous = Native.SetThreadDpiAwarenessContext(new IntPtr(-4)); }
            catch (EntryPointNotFoundException) { }
            try
            {
                RECT r;
                Native.DwmGetWindowAttribute(window, 9, out r, Marshal.SizeOf(typeof(RECT)));
                uint dpi = 96;
                try { dpi = Native.GetDpiForWindow(window); }
                catch (EntryPointNotFoundException) { }
                return new int[] { r.Left, r.Top, r.Right, r.Bottom, (int)dpi };
            }
            finally
            {
                if (previous != IntPtr.Zero) { Native.SetThreadDpiAwarenessContext(previous); }
            }
        }

        // desvio: esquerda, topo, direita, base (pixels reais) e escala da janela
        double[] Desvio(Process p)
        {
            int[] v = VisibleWindow(p.MainWindowHandle);
            return new double[] { v[0] - alvo[0], v[1] - alvo[1], v[2] - alvo[2], v[3] - alvo[3], v[4] / 96.0, v[0], v[1], v[2], v[3], v[4] };
        }

        static bool Certo(double[] d)
        {
            return Math.Abs(d[0]) <= 2 && Math.Abs(d[1]) <= 2 && Math.Abs(d[2]) <= 2 && Math.Abs(d[3]) <= 2;
        }

        void RelatarMedida(string prefixo, double[] d)
        {
            log(string.Format("{0}: janela visível ({1},{2})-({3},{4}), DPI {5}; alvo ({6},{7})-({8},{9}).", prefixo, d[5], d[6], d[7], d[8], d[9], alvo[0], alvo[1], alvo[2], alvo[3]), 0);
        }

        public bool Run(Plan plan, bool abrirAoLigar, bool abrir)
        {
            try
            {
                browser = FindBrowser();
                if (browser == null)
                {
                    log("Não encontrei o Google Chrome neste computador. Instale o Chrome e tente de novo.", 3);
                    return false;
                }
                if (plan == null || plan.A == null)
                {
                    log("Não consegui medir os monitores.", 3);
                    return false;
                }
                log("Navegador: " + browser, 0);
                x = plan.X; y = plan.Y; w = plan.W; h = plan.H;
                alvo = plan.Target;
                url = plan.Url;
                perfil = Path.Combine(Environment.GetEnvironmentVariable("LOCALAPPDATA"), @"NanaComanda\estacao");
                atalho = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), Nome + ".lnk");
                inicializar = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Startup), Nome + ".lnk");

                log(string.Format("Janela: posição {0},{1}, tamanho {2}x{3}", x, y, w, h), 0);
                log("Endereço: " + url, 0);
                Directory.CreateDirectory(perfil);
                SaveShortcut();
                log("Atalho criado na área de trabalho: " + Nome, 1);

                if (abrirAoLigar)
                {
                    File.Copy(atalho, inicializar, true);
                    log("O sistema vai abrir sozinho quando o computador ligar.", 1);
                }
                else if (File.Exists(inicializar))
                {
                    File.Delete(inicializar);
                    log("O sistema não vai mais abrir sozinho ao ligar.", 0);
                }
                foreach (string aviso in plan.Warnings) { log("Aviso: " + aviso, 2); }

                if (!abrir) { return true; }

                if (FindWindow() != null)
                {
                    log("Fechando a janela do Nana Comanda que estava aberta, para conferir a posição...", 0);
                    CloseWindow();
                }
                log("Abrindo e conferindo a posição da janela...", 0);
                Process janela = OpenWindow();
                if (janela == null)
                {
                    log("A janela não abriu a tempo para conferir. Abra o atalho e veja se cobre os monitores.", 2);
                    return true;
                }
                double[] d = Desvio(janela);
                RelatarMedida("Medida", d);
                if (!Certo(d))
                {
                    double e = d[4];
                    x = x - (int)Math.Round(d[0] / e);
                    y = y - (int)Math.Round(d[1] / e);
                    w = w - (int)Math.Round((d[2] - d[0]) / e);
                    h = h - (int)Math.Round((d[3] - d[1]) / e);
                    log(string.Format("Corrigindo as bordas invisíveis do Windows: posição {0},{1}, tamanho {2}x{3}", x, y, w, h), 0);
                    SaveShortcut();
                    CloseWindow();
                    janela = OpenWindow();
                    if (janela != null)
                    {
                        d = Desvio(janela);
                        RelatarMedida("Nova medida", d);
                    }
                }
                if (janela != null && Certo(d))
                {
                    log("Janela conferida: cobre os monitores certinho.", 1);
                }
                else
                {
                    log(string.Format("A janela ficou um pouco fora do lugar (esquerda {0}, topo {1}, direita {2}, base {3} px). Ela funciona, mas pode sobrar uma faixa na borda.", d[0], d[1], d[2], d[3]), 2);
                }
                log("Argumentos finais: " + Argumentos(), 0);
                return true;
            }
            catch (Exception ex)
            {
                log("Algo deu errado: " + ex.Message, 3);
                return false;
            }
        }
    }

    class MonitorPanel : Panel
    {
        public List<Mon> Mons = new List<Mon>();
        public Mon Balcao;
        public Mon Cozinha;
        public event Action<Mon> MonitorClicked;
        readonly List<KeyValuePair<Rectangle, Mon>> hits = new List<KeyValuePair<Rectangle, Mon>>();

        public MonitorPanel()
        {
            DoubleBuffered = true;
            ResizeRedraw = true;
            Cursor = Cursors.Hand;
        }

        protected override void OnPaint(PaintEventArgs e)
        {
            Graphics g = e.Graphics;
            g.Clear(Color.FromArgb(0xFF, 0xF2, 0xD9));
            g.SmoothingMode = System.Drawing.Drawing2D.SmoothingMode.AntiAlias;
            hits.Clear();
            if (Mons.Count == 0) { return; }
            int minX = int.MaxValue, minY = int.MaxValue, maxX = int.MinValue, maxY = int.MinValue;
            foreach (Mon m in Mons)
            {
                minX = Math.Min(minX, m.R.Bounds.Left);
                minY = Math.Min(minY, m.R.Bounds.Top);
                maxX = Math.Max(maxX, m.R.Bounds.Right);
                maxY = Math.Max(maxY, m.R.Bounds.Bottom);
            }
            int pad = (int)(14 * g.DpiX / 96f);
            double sx = (double)(Width - 2 * pad) / (maxX - minX);
            double sy = (double)(Height - 2 * pad) / (maxY - minY);
            double s = Math.Min(sx, sy);
            double offX = (Width - (maxX - minX) * s) / 2.0;
            double offY = (Height - (maxY - minY) * s) / 2.0;
            using (Font big = new Font("Segoe UI", 20f, FontStyle.Bold))
            using (Font small = new Font("Segoe UI", 10f))
            using (StringFormat center = new StringFormat())
            {
                center.Alignment = StringAlignment.Center;
                center.LineAlignment = StringAlignment.Center;
                foreach (Mon m in Mons)
                {
                    Rectangle r = new Rectangle(
                        (int)Math.Round(offX + (m.R.Bounds.Left - minX) * s),
                        (int)Math.Round(offY + (m.R.Bounds.Top - minY) * s),
                        (int)Math.Round((m.R.Bounds.Right - m.R.Bounds.Left) * s),
                        (int)Math.Round((m.R.Bounds.Bottom - m.R.Bounds.Top) * s));
                    Rectangle inner = new Rectangle(r.X + 2, r.Y + 2, r.Width - 4, r.Height - 4);
                    hits.Add(new KeyValuePair<Rectangle, Mon>(r, m));
                    Color fill = Color.White;
                    Color text = Color.FromArgb(0x16, 0x16, 0x16);
                    string label = null;
                    if (object.ReferenceEquals(m, Balcao)) { fill = Color.FromArgb(0xD6, 0x28, 0x28); text = Color.White; label = "BALCÃO"; }
                    else if (object.ReferenceEquals(m, Cozinha)) { fill = Color.FromArgb(0x16, 0x16, 0x16); text = Color.White; label = "COZINHA"; }
                    using (SolidBrush b = new SolidBrush(fill)) { g.FillRectangle(b, inner); }
                    using (Pen pen = new Pen(Color.FromArgb(0x16, 0x16, 0x16), 3f)) { g.DrawRectangle(pen, r); }
                    string res = string.Format("{0} × {1}  ({2}%)", m.PhysWidth, m.PhysHeight, (int)Math.Round(m.Scale * 100));
                    using (SolidBrush tb = new SolidBrush(text))
                    {
                        if (label != null)
                        {
                            RectangleF top = new RectangleF(inner.X, inner.Y, inner.Width, inner.Height * 0.62f);
                            RectangleF bottom = new RectangleF(inner.X, inner.Y + inner.Height * 0.55f, inner.Width, inner.Height * 0.40f);
                            g.DrawString(label, big, tb, top, center);
                            g.DrawString(res, small, tb, bottom, center);
                        }
                        else
                        {
                            g.DrawString(res, small, tb, new RectangleF(inner.X, inner.Y, inner.Width, inner.Height), center);
                        }
                    }
                }
            }
        }

        protected override void OnMouseClick(MouseEventArgs e)
        {
            base.OnMouseClick(e);
            foreach (KeyValuePair<Rectangle, Mon> kv in hits)
            {
                if (kv.Key.Contains(e.Location))
                {
                    if (MonitorClicked != null) { MonitorClicked(kv.Value); }
                    return;
                }
            }
        }
    }

    class MainForm : Form
    {
        static readonly Color Red = Color.FromArgb(0xD6, 0x28, 0x28);
        static readonly Color Cream = Color.FromArgb(0xFF, 0xF2, 0xD9);
        static readonly Color Ink = Color.FromArgb(0x16, 0x16, 0x16);
        static readonly Color Green = Color.FromArgb(0x1F, 0x8A, 0x50);
        static readonly Color Yellow = Color.FromArgb(0xFF, 0xB7, 0x03);

        readonly string baseUrl;
        readonly MonitorPanel panel = new MonitorPanel();
        readonly Label warnings = new Label();
        readonly RichTextBox status = new RichTextBox();
        readonly CheckBox startup = new CheckBox();
        readonly Button btnInstalar = new Button();
        readonly Button btnConfig = new Button();
        readonly Button btnMedir = new Button();
        readonly TableLayoutPanel table = new TableLayoutPanel();
        List<Mon> mons = new List<Mon>();

        public MainForm(string baseUrl)
        {
            this.baseUrl = baseUrl;
            AutoScaleDimensions = new SizeF(96f, 96f);
            AutoScaleMode = AutoScaleMode.Dpi;
            Text = "Nana Comanda — PC do balcão";
            Font = new Font("Segoe UI", 11f);
            BackColor = Cream;
            ForeColor = Ink;
            ClientSize = new Size(960, 780);
            MinimumSize = new Size(760, 640);
            StartPosition = FormStartPosition.CenterScreen;
            try { Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath); } catch (Exception) { }

            Panel header = new Panel();
            header.Dock = DockStyle.Top;
            header.Height = 70;
            header.BackColor = Red;
            Label title = new Label();
            title.Text = "Nana Comanda — PC do balcão";
            title.Font = new Font("Segoe UI", 20f, FontStyle.Bold);
            title.ForeColor = Color.White;
            title.Dock = DockStyle.Fill;
            title.TextAlign = ContentAlignment.MiddleLeft;
            title.Padding = new Padding(16, 0, 0, 0);
            header.Controls.Add(title);

            Panel footer = new Panel();
            footer.Dock = DockStyle.Bottom;
            footer.Height = 84;
            footer.BackColor = Cream;
            footer.Padding = new Padding(16, 12, 16, 12);

            startup.Text = "Abrir sozinho quando o computador ligar";
            startup.Checked = true;
            startup.AutoSize = true;
            startup.Font = new Font("Segoe UI", 12f);
            startup.Dock = DockStyle.Left;
            startup.TextAlign = ContentAlignment.MiddleLeft;

            btnInstalar.Text = "Instalar";
            btnInstalar.Font = new Font("Segoe UI", 16f, FontStyle.Bold);
            btnInstalar.BackColor = Red;
            btnInstalar.ForeColor = Color.White;
            btnInstalar.FlatStyle = FlatStyle.Flat;
            btnInstalar.FlatAppearance.BorderSize = 0;
            btnInstalar.Dock = DockStyle.Right;
            btnInstalar.Width = 220;
            btnInstalar.Cursor = Cursors.Hand;
            btnInstalar.Click += delegate { Instalar(); };
            footer.Controls.Add(btnInstalar);
            footer.Controls.Add(startup);

            table.Dock = DockStyle.Fill;
            table.ColumnCount = 1;
            table.RowCount = 4;
            table.Padding = new Padding(16, 12, 16, 4);
            table.RowStyles.Add(new RowStyle(SizeType.Absolute, 250));
            table.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            table.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            table.RowStyles.Add(new RowStyle(SizeType.Percent, 100));

            panel.Dock = DockStyle.Fill;
            panel.MonitorClicked += OnMonitorClicked;

            warnings.AutoSize = true;
            warnings.BackColor = Yellow;
            warnings.ForeColor = Ink;
            warnings.Font = new Font("Segoe UI", 11f);
            warnings.Padding = new Padding(10, 8, 10, 8);
            warnings.Margin = new Padding(0, 10, 0, 0);
            warnings.Visible = false;

            FlowLayoutPanel buttons = new FlowLayoutPanel();
            buttons.AutoSize = true;
            buttons.Dock = DockStyle.Fill;
            buttons.Margin = new Padding(0, 10, 0, 0);
            StyleSecondary(btnConfig, "Abrir configurações de tela");
            StyleSecondary(btnMedir, "Medir de novo");
            btnConfig.Click += delegate
            {
                try { Process.Start("ms-settings:display"); } catch (Exception) { }
            };
            btnMedir.Click += delegate { Medir(); };
            buttons.Controls.Add(btnConfig);
            buttons.Controls.Add(btnMedir);

            status.ReadOnly = true;
            status.BackColor = Color.White;
            status.BorderStyle = BorderStyle.FixedSingle;
            status.Font = new Font("Segoe UI", 11f);
            status.Dock = DockStyle.Fill;
            status.Margin = new Padding(0, 10, 0, 0);
            status.TabStop = false;

            table.Controls.Add(panel, 0, 0);
            table.Controls.Add(warnings, 0, 1);
            table.Controls.Add(buttons, 0, 2);
            table.Controls.Add(status, 0, 3);

            Controls.Add(table);
            Controls.Add(footer);
            Controls.Add(header);

            table.SizeChanged += delegate { FixWarningsWidth(); };
            Shown += delegate { Medir(); };
        }

        void StyleSecondary(Button b, string text)
        {
            b.Text = text;
            b.AutoSize = true;
            b.AutoSizeMode = AutoSizeMode.GrowAndShrink;
            b.Font = new Font("Segoe UI", 11f);
            b.BackColor = Color.White;
            b.ForeColor = Ink;
            b.FlatStyle = FlatStyle.Flat;
            b.FlatAppearance.BorderColor = Ink;
            b.Padding = new Padding(10, 6, 10, 6);
            b.Margin = new Padding(0, 0, 12, 0);
            b.Cursor = Cursors.Hand;
        }

        void FixWarningsWidth()
        {
            warnings.MaximumSize = new Size(Math.Max(100, table.ClientSize.Width - table.Padding.Horizontal), 0);
        }

        void Medir()
        {
            mons = Geometry.GetMonitors();
            panel.Mons = mons;
            Mon[] pair = Geometry.FindPair(mons);
            if (pair != null) { panel.Balcao = pair[0]; panel.Cozinha = pair[1]; }
            else
            {
                panel.Balcao = null;
                panel.Cozinha = null;
                foreach (Mon m in mons) { if (m.Primary) { panel.Balcao = m; break; } }
            }
            Atualizar();
            status.Text = mons.Count > 1
                ? "Confira no desenho qual monitor é o BALCÃO e qual é a COZINHA. Para trocar, clique no monitor do balcão.\nDepois clique em Instalar."
                : "Só um monitor ligado. Ligue o monitor da cozinha, clique em Medir de novo e depois em Instalar.";
        }

        void OnMonitorClicked(Mon m)
        {
            panel.Balcao = m;
            panel.Cozinha = Geometry.FindRight(mons, m);
            Atualizar();
        }

        Plan CurrentPlan()
        {
            return Geometry.BuildPlan(mons, panel.Balcao, panel.Cozinha, baseUrl);
        }

        void Atualizar()
        {
            panel.Invalidate();
            Plan p = CurrentPlan();
            if (p.Warnings.Count > 0)
            {
                warnings.Text = string.Join("\r\n\r\n", p.Warnings.ToArray());
                warnings.Visible = true;
            }
            else { warnings.Visible = false; }
            FixWarningsWidth();
        }

        void Append(string text, int kind)
        {
            Color c = Ink;
            FontStyle fs = FontStyle.Regular;
            if (kind == 1) { c = Green; fs = FontStyle.Bold; }
            else if (kind == 2) { c = Color.FromArgb(0x9A, 0x62, 0x00); }
            else if (kind == 3) { c = Red; fs = FontStyle.Bold; }
            else if (kind == 4) { c = Green; fs = FontStyle.Bold; }
            status.SelectionStart = status.TextLength;
            status.SelectionLength = 0;
            status.SelectionColor = c;
            status.SelectionFont = new Font("Segoe UI", kind == 4 ? 16f : 11f, fs);
            status.AppendText(text + "\r\n");
            status.ScrollToCaret();
        }

        void Instalar()
        {
            Plan plan = CurrentPlan();
            bool ligar = startup.Checked;
            btnInstalar.Enabled = false;
            btnMedir.Enabled = false;
            btnConfig.Enabled = false;
            status.Clear();
            Append("Instalando... não mexa nas janelas por alguns segundos.", 0);
            Thread t = new Thread(delegate()
            {
                LogFn fn = delegate(string text, int kind)
                {
                    BeginInvoke(new Action(delegate { Append(text, kind); }));
                };
                Installer inst = new Installer(fn);
                bool ok = inst.Run(plan, ligar, true);
                BeginInvoke(new Action(delegate
                {
                    if (ok)
                    {
                        Append("", 0);
                        Append("Pronto!", 4);
                        Append("1. Na janela que abriu, entre com o usuário do balcão e marque \"Manter conectado neste aparelho\".", 0);
                        Append("2. Pronto: a cozinha já aparece no monitor da direita.", 0);
                    }
                    else
                    {
                        Append("Não foi possível terminar. Veja a mensagem acima.", 3);
                    }
                    btnInstalar.Enabled = true;
                    btnMedir.Enabled = true;
                    btnConfig.Enabled = true;
                }));
            });
            t.SetApartmentState(ApartmentState.STA);
            t.IsBackground = true;
            t.Start();
        }
    }

    static class Program
    {
        [STAThread]
        static int Main(string[] args)
        {
            string baseUrl = Installer.DefaultUrl;
            bool headless = false, naoIniciar = false, semAbrir = false;
            foreach (string a in args)
            {
                if (a == "--instalar") { headless = true; }
                else if (a == "--nao-iniciar-com-windows") { naoIniciar = true; }
                else if (a == "--sem-abrir") { semAbrir = true; }
                else if (a.StartsWith("--url=", StringComparison.OrdinalIgnoreCase)) { baseUrl = a.Substring(6).Trim('"'); }
            }
            if (headless)
            {
                string logPath = Path.Combine(Path.GetTempPath(), "nana-instalador.log");
                try { File.WriteAllText(logPath, "", new UTF8Encoding(true)); } catch (Exception) { }
                LogFn fn = delegate(string text, int kind)
                {
                    try { File.AppendAllText(logPath, DateTime.Now.ToString("HH:mm:ss") + " " + text + "\r\n", new UTF8Encoding(true)); } catch (Exception) { }
                };
                bool ok = false;
                try
                {
                    List<Mon> mons = Geometry.GetMonitors();
                    foreach (Mon m in mons)
                    {
                        fn(string.Format("Monitor {0}: real {1}x{2} em ({3},{4}), lógico {5}, escala {6}", m.Device, m.PhysWidth, m.PhysHeight, m.R.Bounds.Left, m.R.Bounds.Top, m.LogicalWidth, m.Scale), 0);
                    }
                    Mon[] pair = Geometry.FindPair(mons);
                    Mon a = pair != null ? pair[0] : null;
                    Mon b = pair != null ? pair[1] : null;
                    Plan plan = Geometry.BuildPlan(mons, a, b, baseUrl);
                    Installer inst = new Installer(fn);
                    ok = inst.Run(plan, !naoIniciar, !semAbrir);
                }
                catch (Exception ex) { fn("Erro: " + ex, 3); }
                fn(ok ? "Resultado: OK" : "Resultado: FALHOU", 0);
                return ok ? 0 : 1;
            }
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new MainForm(baseUrl));
            return 0;
        }
    }
}
