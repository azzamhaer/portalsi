'use client';

import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';

export type Lang = 'id' | 'en';
const COOKIE = 'portalsi_lang';
const COOKIE_DOMAIN = '.portalsi.com';

function readCookieLang(): Lang | null {
  if (typeof document === 'undefined') return null;
  const m = document.cookie.match(/(?:^|;\s*)portalsi_lang=(id|en)/);
  return m ? (m[1] as Lang) : null;
}

function writeCookieLang(l: Lang) {
  if (typeof document === 'undefined') return;
  try {
    document.cookie = `${COOKIE}=${l};path=/;max-age=31536000;samesite=lax;domain=${COOKIE_DOMAIN}`;
  } catch {}
  try {
    document.cookie = `${COOKIE}=${l};path=/;max-age=31536000;samesite=lax`;
  } catch {}
  try {
    localStorage.setItem(COOKIE, l);
  } catch {}
  document.documentElement.lang = l;
}

// ── Kamus ──
const DICT: Record<Lang, Record<string, string>> = {
  id: {
    // footer / umum
    'footer.free': 'Gratis',
    'footer.secure': 'Aman',
    'footer.integrated': 'Terintegrasi Akun Portal SI',
    'common.free': 'Gratis',
    'lang.label': 'Bahasa',
    // not found
    'nf.title': 'Halaman tidak ditemukan',
    'nf.desc': 'Sepertinya kamu tersesat di ruang kosong.',
    'nf.home': 'Kembali ke Beranda',
    // FAQ
    'faq.btn': 'Bantuan / FAQ',
    'faq.title': 'Pusat Bantuan & FAQ',
    'faq.q1': 'Bagaimana cara bergabung ke meeting?',
    'faq.a1': "Masukkan ID Meeting atau tautan yang diberikan oleh penyelenggara pada halaman utama, lalu klik 'Gabung'.",
    'faq.q2': 'Apakah saya perlu membuat akun?',
    'faq.a2': 'Untuk membuat meeting, Anda perlu login dengan akun Portal SI. Untuk bergabung sebagai peserta, cukup isi nama dan kode meeting dari penyelenggara.',
    'faq.q3': 'Mengapa kamera/mikrofon saya tidak berfungsi?',
    'faq.a3': 'Pastikan Anda telah memberikan izin akses (allow) untuk kamera dan mikrofon pada pop-up yang muncul di browser Anda.',
    'faq.q4': 'Bagaimana cara melakukan Share Screen?',
    'faq.a4': "Saat berada di dalam meeting, klik tombol 'Share Screen' (ikon monitor) di bagian bawah layar untuk membagikan layar Anda.",
    'faq.q5': 'Apakah ada batasan waktu?',
    'faq.a5': 'Saat ini tidak ada batasan waktu untuk menggunakan Portal SI Meet.',
    'faq.needHelp': 'Masih butuh bantuan?',
    'faq.contactVia': 'Hubungi tim support kami via WhatsApp',
    'faq.contactBtn': 'Hubungi Support',
    // home hero
    'hero.h1a': 'Video meeting,',
    'hero.h1b': 'dibuat sesederhana mungkin.',
    'hero.sub': 'Setiap percakapan yang bermakna dimulai dengan cara yang sederhana. Buat ruang meeting dalam hitungan detik, lalu biarkan percakapan mengalir tanpa hambatan.',
    'hero.tabCreate': 'Buat Meeting',
    'hero.tabJoin': 'Gabung Meeting',
    'hero.roomCreated': 'Rapat Berhasil Dibuat',
    'hero.scheduledFor': 'Dijadwalkan untuk:',
    'hero.shareLink': 'Link Rapat (Bagikan ini)',
    'hero.copied': 'Disalin!',
    'hero.copy': 'Copy',
    'hero.password': 'Password',
    'hero.createAnother': 'Buat Lainnya',
    'hero.startNow': 'Mulai Sekarang',
    'hero.checkingSession': 'Mengecek sesi Portal SI...',
    'hero.accountLabel': 'Akun Portal SI',
    'hero.logoutAria': 'Keluar dari akun Portal SI',
    'hero.modeInstant': 'Instan',
    'hero.modeLater': 'Buat Nanti',
    'hero.modeSchedule': 'Jadwalkan',
    'hero.hostName': 'Nama Host',
    'hero.nameFollows': 'Nama mengikuti akun Portal SI yang login.',
    'hero.date': 'Tanggal',
    'hero.time': 'Waktu',
    'hero.usePassword': 'Buat password room',
    'hero.pwPlaceholder': 'Buat password...',
    'hero.starting': 'Memulai...',
    'hero.creating': 'Membuat...',
    'hero.startInstant': 'Mulai Rapat Instan',
    'hero.getInfo': 'Dapatkan Info Rapat',
    'hero.schedule': 'Jadwalkan Rapat',
    'hero.yourName': 'Nama Anda',
    'hero.enterName': 'Masukkan nama',
    'hero.meetingCode': 'Kode Meeting',
    'hero.codeExample': 'Contoh: ABCDEF',
    'hero.joinNow': 'Gabung Sekarang',
    'auth.checkEmail': 'Cek email kamu',
    'auth.verifySent1': 'Link verifikasi sudah dikirim ke',
    'auth.verifySent2': '. Verifikasi email dulu, lalu masuk untuk mulai membuat meeting.',
    'auth.backToLogin': 'Kembali ke Masuk',
    'auth.loginWith': 'Login pakai akun Portal SI',
    'auth.loginDesc': 'Akun Portal SI wajib untuk membuat meeting. Peserta tetap bisa gabung dari tab sebelah cukup dengan nama dan kode meeting.',
    'auth.tabLogin': 'Masuk',
    'auth.tabRegister': 'Daftar',
    'auth.loginId': 'Email atau Username Portal SI',
    'auth.loginIdPlaceholder': 'username atau email',
    'auth.pw': 'Password Portal SI',
    'auth.pwPlaceholder': 'Masukkan password...',
    'auth.loggingIn': 'Masuk...',
    'auth.loginCreate': 'Masuk dan Buat Meeting',
    'auth.regUsername': 'Username Portal SI',
    'auth.regUsernamePlaceholder': 'contoh: ahmad.santri',
    'auth.fullName': 'Nama Lengkap',
    'auth.fullNamePlaceholder': 'Masukkan nama lengkap',
    'auth.email': 'Email',
    'auth.emailPlaceholder': 'nama@email.com',
    'auth.regPw': 'Password',
    'auth.regPwPlaceholder': 'Minimal 6 karakter',
    'auth.registering': 'Mendaftar...',
    'auth.createAccount': 'Buat Akun Portal SI',
    'err.authRequired': 'Email/username dan password Portal SI wajib diisi.',
    'err.loginFail': 'Login Portal SI gagal.',
    'err.usernameChars': 'Username hanya boleh berisi huruf, angka, titik, dan underscore.',
    'err.regRequired': 'Username, nama lengkap, email, dan password wajib diisi.',
    'err.pwMin': 'Password minimal 6 karakter.',
    'err.regFail': 'Pendaftaran akun Portal SI gagal.',
    'err.loginToCreate': 'Login akun Portal SI dulu untuk membuat meeting.',
    'err.hostEmpty': 'Nama host tidak boleh kosong.',
    'err.pickDateTime': 'Pilih tanggal dan waktu rapat.',
    'err.pastTime': 'Waktu rapat tidak boleh di masa lalu.',
    'err.createRoomFail': 'Gagal membuat ruang.',
    'err.roomIdInvalid': 'Room ID tidak valid.',
    'err.nameEmpty': 'Nama tidak boleh kosong.',
    // RoomClient
    'rc.passwordWrong': 'Password salah.',
    'rc.accessDenied': 'Akses ditolak.',
    'rc.joinFail': 'Gagal bergabung.',
    'rc.observerInvalid': 'Link observer moderator tidak valid.',
    'rc.modFail': 'Gagal masuk sebagai moderator.',
    'rc.roomInfoFail': 'Tidak bisa memuat info ruang.',
    'rc.connecting': 'Menghubungkan…',
    'rc.loading': 'Memuat…',
    'rc.notFoundTitle': 'Ruang tidak ditemukan',
    'rc.notFoundMsg': 'Room ID salah atau meeting sudah berakhir.',
    'rc.errorTitle': 'Terjadi kesalahan',
    'rc.unknownError': 'Unknown error.',
    // MeetingRoom
    'mr.configTitle': 'Konfigurasi Bermasalah',
    'mr.configMsg': 'NEXT_PUBLIC_LIVEKIT_URL belum diset.',
    'mr.disconnectedTitle': 'Koneksi Terputus',
    'mr.endedByHost': 'Rapat ini telah diakhiri oleh Host.',
    'mr.removed': 'Anda telah dikeluarkan dari rapat.',
    'mr.serverLost': 'Koneksi terputus dari server.',
    'mr.disconnectedReason': 'Terputus dari rapat. Alasan:',
    'mr.accessDeniedTitle': 'Akses Ditolak',
    'mr.meetingDoneTitle': 'Rapat Selesai',
    'mr.captionsUnsupported': 'Browser Anda tidak mendukung Live Captions.',
    'mr.uploadingRec': 'Mengunggah rekaman...',
    'mr.recAvailable': 'Rekaman meeting telah tersedia.',
    'mr.uploadRecFail': 'Gagal mengunggah rekaman: ',
    'mr.uploadRecErr': 'Terjadi kesalahan saat mengunggah rekaman.',
    'mr.recStartFail': 'Gagal memulai rekaman. Pastikan Anda memberikan izin akses layar & audio sistem.',
    'mr.endedByYou': 'Anda telah mengakhiri rapat ini.',
    'mr.anon': 'Anonim',
    // VideoStage / BottomBar / panels
    'vs.sharing': 'sedang berbagi layar',
    'bar.micNotFound': 'Mic tidak terdeteksi',
    'bar.micOff': 'Matikan Mic',
    'bar.micOn': 'Nyalakan Mic',
    'bar.camNotFound': 'Kamera tidak terdeteksi',
    'bar.camOff': 'Matikan Kamera',
    'bar.camOn': 'Nyalakan Kamera',
    'bar.stopShare': 'Stop Share',
    'bar.share': 'Share Layar',
    'bar.shareDisabled': 'Screen share dinonaktifkan',
    'bar.react': 'Reaksi',
    'bar.reactDisabled': 'Reaksi dinonaktifkan',
    'bar.lowerHand': 'Turunkan Tangan',
    'bar.raiseHand': 'Angkat Tangan',
    'bar.stopRec': 'Hentikan Rekaman',
    'bar.startRec': 'Mulai Rekaman',
    'bar.zoomUnsupported': 'Zoom tidak didukung oleh perangkat ini.',
    'sp.qHighest': 'Highest (HD, Lebih panas & boros baterai)',
    'sp.qBalanced': 'Balanced (Optimal)',
    'sp.qLowest': 'Lowest (Hemat baterai & internet, tidak panas)',
    'ip.roomPassword': 'Password Room',
    'timer.title': 'Atur Timer',
    'timer.desc': 'Masukkan durasi timer dalam menit. Timer akan ditampilkan kepada seluruh peserta.',
    'timer.minutes': 'Menit',
    'timer.seconds': 'Detik',
    'timer.cancel': 'Batal',
    'timer.start': 'Mulai Timer',
    'chat.fileMax': 'Ukuran file maksimal 20MB.',
    'chat.uploadErr': 'Error mengunggah.',
    'chat.sendingFile': 'Mengirim berkas',
    'chat.deletePoll': 'Apakah Anda yakin ingin menghapus polling ini?',
    'pp.demote': 'Hapus status Admin dari',
    'pp.kick': 'Keluarkan',
    'pp.kickSuffix': 'dari rapat ini?',
    'pj.joinNow': 'Gabung Sekarang',
    'pj.camOff': 'Kamera mati',
    'pj.ready': 'Siap bergabung?',
    'pj.rejected': 'Permintaan Ditolak',
    'pj.rejectedDesc': 'Host menolak akses Anda ke meeting ini.',
    'pj.waiting': 'Menunggu persetujuan host...',
    'pj.waitingDesc': 'Host akan menerima Anda sebentar lagi',
    'pj.mic': 'Mikrofon',
    'pj.camera': 'Kamera',
    'pj.default': 'Default',
    'chat.reply': 'Balas',
    'chat.typePlaceholder': 'Ketik pesan...',
    'ip.rename': 'Ganti Nama',
    'pp.search': 'Cari peserta...',
    'sp.allowRename': 'Izinkan Ganti Nama',
  },
  en: {
    'footer.free': 'Free',
    'footer.secure': 'Secure',
    'footer.integrated': 'Portal SI Account Integrated',
    'common.free': 'Free',
    'lang.label': 'Language',
    'nf.title': 'Page not found',
    'nf.desc': 'Looks like you wandered into an empty room.',
    'nf.home': 'Back to Home',
    'faq.btn': 'Help / FAQ',
    'faq.title': 'Help Center & FAQ',
    'faq.q1': 'How do I join a meeting?',
    'faq.a1': "Enter the Meeting ID or link given by the organizer on the home page, then click 'Join'.",
    'faq.q2': 'Do I need to create an account?',
    'faq.a2': 'To create a meeting, you need to sign in with a Portal SI account. To join as a participant, just enter your name and the meeting code from the organizer.',
    'faq.q3': "Why aren't my camera/microphone working?",
    'faq.a3': 'Make sure you have granted camera and microphone access (allow) on the pop-up that appears in your browser.',
    'faq.q4': 'How do I Share Screen?',
    'faq.a4': "While in a meeting, click the 'Share Screen' button (monitor icon) at the bottom of the screen to share your screen.",
    'faq.q5': 'Is there a time limit?',
    'faq.a5': 'There is currently no time limit for using Portal SI Meet.',
    'faq.needHelp': 'Still need help?',
    'faq.contactVia': 'Contact our support team via WhatsApp',
    'faq.contactBtn': 'Contact Support',
    'hero.h1a': 'Video meetings,',
    'hero.h1b': 'made as simple as possible.',
    'hero.sub': 'Every meaningful conversation starts simply. Create a meeting room in seconds, then let the conversation flow without friction.',
    'hero.tabCreate': 'Create Meeting',
    'hero.tabJoin': 'Join Meeting',
    'hero.roomCreated': 'Meeting Created',
    'hero.scheduledFor': 'Scheduled for:',
    'hero.shareLink': 'Meeting Link (Share this)',
    'hero.copied': 'Copied!',
    'hero.copy': 'Copy',
    'hero.password': 'Password',
    'hero.createAnother': 'Create Another',
    'hero.startNow': 'Start Now',
    'hero.checkingSession': 'Checking Portal SI session...',
    'hero.accountLabel': 'Portal SI account',
    'hero.logoutAria': 'Sign out of Portal SI account',
    'hero.modeInstant': 'Instant',
    'hero.modeLater': 'Create Later',
    'hero.modeSchedule': 'Schedule',
    'hero.hostName': 'Host name',
    'hero.nameFollows': 'The name follows your signed-in Portal SI account.',
    'hero.date': 'Date',
    'hero.time': 'Time',
    'hero.usePassword': 'Set a room password',
    'hero.pwPlaceholder': 'Create a password...',
    'hero.starting': 'Starting...',
    'hero.creating': 'Creating...',
    'hero.startInstant': 'Start Instant Meeting',
    'hero.getInfo': 'Get Meeting Info',
    'hero.schedule': 'Schedule Meeting',
    'hero.yourName': 'Your name',
    'hero.enterName': 'Enter your name',
    'hero.meetingCode': 'Meeting Code',
    'hero.codeExample': 'e.g. ABCDEF',
    'hero.joinNow': 'Join Now',
    'auth.checkEmail': 'Check your email',
    'auth.verifySent1': 'A verification link has been sent to',
    'auth.verifySent2': '. Verify your email first, then sign in to start creating meetings.',
    'auth.backToLogin': 'Back to Sign In',
    'auth.loginWith': 'Sign in with your Portal SI account',
    'auth.loginDesc': 'A Portal SI account is required to create meetings. Participants can still join from another tab with just a name and the meeting code.',
    'auth.tabLogin': 'Sign In',
    'auth.tabRegister': 'Sign Up',
    'auth.loginId': 'Portal SI email or username',
    'auth.loginIdPlaceholder': 'username or email',
    'auth.pw': 'Portal SI password',
    'auth.pwPlaceholder': 'Enter your password...',
    'auth.loggingIn': 'Signing in...',
    'auth.loginCreate': 'Sign In and Create Meeting',
    'auth.regUsername': 'Portal SI username',
    'auth.regUsernamePlaceholder': 'e.g. ahmad.santri',
    'auth.fullName': 'Full name',
    'auth.fullNamePlaceholder': 'Enter your full name',
    'auth.email': 'Email',
    'auth.emailPlaceholder': 'name@email.com',
    'auth.regPw': 'Password',
    'auth.regPwPlaceholder': 'At least 6 characters',
    'auth.registering': 'Signing up...',
    'auth.createAccount': 'Create Portal SI Account',
    'err.authRequired': 'Portal SI email/username and password are required.',
    'err.loginFail': 'Portal SI sign-in failed.',
    'err.usernameChars': 'Username may only contain letters, numbers, dots, and underscores.',
    'err.regRequired': 'Username, full name, email, and password are required.',
    'err.pwMin': 'Password must be at least 6 characters.',
    'err.regFail': 'Portal SI account registration failed.',
    'err.loginToCreate': 'Sign in with your Portal SI account first to create a meeting.',
    'err.hostEmpty': 'Host name cannot be empty.',
    'err.pickDateTime': 'Pick a meeting date and time.',
    'err.pastTime': 'Meeting time cannot be in the past.',
    'err.createRoomFail': 'Failed to create the room.',
    'err.roomIdInvalid': 'Invalid Room ID.',
    'err.nameEmpty': 'Name cannot be empty.',
    'rc.passwordWrong': 'Wrong password.',
    'rc.accessDenied': 'Access denied.',
    'rc.joinFail': 'Failed to join.',
    'rc.observerInvalid': 'Invalid moderator observer link.',
    'rc.modFail': 'Failed to join as moderator.',
    'rc.roomInfoFail': "Couldn't load room info.",
    'rc.connecting': 'Connecting…',
    'rc.loading': 'Loading…',
    'rc.notFoundTitle': 'Room not found',
    'rc.notFoundMsg': 'The Room ID is wrong or the meeting has ended.',
    'rc.errorTitle': 'Something went wrong',
    'rc.unknownError': 'Unknown error.',
    'mr.configTitle': 'Configuration Problem',
    'mr.configMsg': 'NEXT_PUBLIC_LIVEKIT_URL is not set.',
    'mr.disconnectedTitle': 'Disconnected',
    'mr.endedByHost': 'This meeting has been ended by the host.',
    'mr.removed': 'You have been removed from the meeting.',
    'mr.serverLost': 'Connection to the server was lost.',
    'mr.disconnectedReason': 'Disconnected from the meeting. Reason:',
    'mr.accessDeniedTitle': 'Access Denied',
    'mr.meetingDoneTitle': 'Meeting Ended',
    'mr.captionsUnsupported': 'Your browser does not support Live Captions.',
    'mr.uploadingRec': 'Uploading recording...',
    'mr.recAvailable': 'The meeting recording is now available.',
    'mr.uploadRecFail': 'Failed to upload recording: ',
    'mr.uploadRecErr': 'An error occurred while uploading the recording.',
    'mr.recStartFail': 'Failed to start recording. Make sure you grant screen & system audio access.',
    'mr.endedByYou': 'You have ended this meeting.',
    'mr.anon': 'Anonymous',
    'vs.sharing': 'is sharing their screen',
    'bar.micNotFound': 'Mic not detected',
    'bar.micOff': 'Turn off Mic',
    'bar.micOn': 'Turn on Mic',
    'bar.camNotFound': 'Camera not detected',
    'bar.camOff': 'Turn off Camera',
    'bar.camOn': 'Turn on Camera',
    'bar.stopShare': 'Stop Share',
    'bar.share': 'Share Screen',
    'bar.shareDisabled': 'Screen share disabled',
    'bar.react': 'React',
    'bar.reactDisabled': 'Reactions disabled',
    'bar.lowerHand': 'Lower Hand',
    'bar.raiseHand': 'Raise Hand',
    'bar.stopRec': 'Stop Recording',
    'bar.startRec': 'Start Recording',
    'bar.zoomUnsupported': 'Zoom is not supported on this device.',
    'sp.qHighest': 'Highest (HD, hotter & more battery)',
    'sp.qBalanced': 'Balanced (Optimal)',
    'sp.qLowest': 'Lowest (Saves battery & data, runs cool)',
    'ip.roomPassword': 'Room Password',
    'timer.title': 'Set Timer',
    'timer.desc': 'Enter the timer duration in minutes. The timer will be shown to all participants.',
    'timer.minutes': 'Minutes',
    'timer.seconds': 'Seconds',
    'timer.cancel': 'Cancel',
    'timer.start': 'Start Timer',
    'chat.fileMax': 'Maximum file size is 20MB.',
    'chat.uploadErr': 'Upload error.',
    'chat.sendingFile': 'Sending a file',
    'chat.deletePoll': 'Are you sure you want to delete this poll?',
    'pp.demote': 'Remove Admin status from',
    'pp.kick': 'Remove',
    'pp.kickSuffix': 'from this meeting?',
    'pj.joinNow': 'Join Now',
    'pj.camOff': 'Camera off',
    'pj.ready': 'Ready to join?',
    'pj.rejected': 'Request Rejected',
    'pj.rejectedDesc': 'The host denied your access to this meeting.',
    'pj.waiting': 'Waiting for host approval...',
    'pj.waitingDesc': 'The host will admit you shortly',
    'pj.mic': 'Microphone',
    'pj.camera': 'Camera',
    'pj.default': 'Default',
    'chat.reply': 'Reply',
    'chat.typePlaceholder': 'Type a message...',
    'ip.rename': 'Rename',
    'pp.search': 'Search participants...',
    'sp.allowRename': 'Allow Rename',
  },
};

interface Ctx {
  lang: Lang;
  setLang: (l: Lang) => void;
  t: (key: string, fallback?: string) => string;
}
const LangCtx = createContext<Ctx>({ lang: 'id', setLang: () => {}, t: (k, f) => f ?? k });

export function LangProvider({ initial = 'id', children }: { initial?: Lang; children: ReactNode }) {
  const [lang, setLangState] = useState<Lang>(initial);
  useEffect(() => {
    const c = readCookieLang();
    if (c && c !== lang) setLangState(c);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const setLang = (l: Lang) => {
    setLangState(l);
    writeCookieLang(l);
  };
  const t = (key: string, fallback?: string) => DICT[lang]?.[key] ?? DICT.id[key] ?? fallback ?? key;
  return <LangCtx.Provider value={{ lang, setLang, t }}>{children}</LangCtx.Provider>;
}

export function useT() {
  return useContext(LangCtx);
}

/** Tombol bahasa mengambang, bisa disembunyikan (retractable). */
export function LangToggle() {
  const { lang, setLang } = useT();
  const [open, setOpen] = useState(false);
  return (
    <div style={{ position: 'fixed', left: 14, bottom: 14, zIndex: 9999 }}>
      {open ? (
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 4,
            padding: 4,
            background: 'rgba(20,22,28,0.92)',
            border: '1px solid rgba(255,255,255,0.12)',
            borderRadius: 999,
            boxShadow: '0 8px 24px rgba(0,0,0,0.35)',
            backdropFilter: 'blur(8px)',
          }}
        >
          {(['id', 'en'] as Lang[]).map((l) => (
            <button
              key={l}
              type="button"
              onClick={() => setLang(l)}
              style={{
                border: 0,
                cursor: 'pointer',
                fontSize: 12,
                fontWeight: 700,
                padding: '5px 11px',
                borderRadius: 999,
                color: lang === l ? '#0a0a0f' : '#c3ccd8',
                background: lang === l ? '#ffffff' : 'transparent',
              }}
            >
              {l.toUpperCase()}
            </button>
          ))}
          <button
            type="button"
            onClick={() => setOpen(false)}
            aria-label="Sembunyikan"
            style={{ border: 0, cursor: 'pointer', background: 'transparent', color: '#9aa4b2', padding: '0 8px 0 4px', fontSize: 14 }}
          >
            ✕
          </button>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => setOpen(true)}
          aria-label="Bahasa / Language"
          title="Bahasa / Language"
          style={{
            display: 'grid',
            placeItems: 'center',
            width: 38,
            height: 38,
            borderRadius: 999,
            border: '1px solid rgba(255,255,255,0.12)',
            background: 'rgba(20,22,28,0.92)',
            color: '#e6e9ef',
            cursor: 'pointer',
            fontSize: 12,
            fontWeight: 800,
            boxShadow: '0 8px 24px rgba(0,0,0,0.35)',
            backdropFilter: 'blur(8px)',
          }}
        >
          {lang.toUpperCase()}
        </button>
      )}
    </div>
  );
}
