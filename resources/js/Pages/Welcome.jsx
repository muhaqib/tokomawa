import { Head, Link, usePage } from "@inertiajs/react";
import StoreBrand from "@/Components/StoreBrand";
import { can } from "@/Utils/authorization";
import { IconArrowUpRight, IconArrowRight, IconMapPin, IconPhone, IconBuildingStore, IconHeartHandshake, IconReceipt, IconShoppingBag, IconMail } from "@tabler/icons-react";

function StoreIllustration() {
    return (
        <svg viewBox="0 0 560 450" className="h-auto w-full" role="img" aria-labelledby="mawa-store-title">
            <title id="mawa-store-title">Ilustrasi etalase Toko Mawa berwarna biru</title>
            <ellipse cx="285" cy="405" rx="217" ry="22" fill="#1946a1" opacity=".15" />
            <circle cx="290" cy="215" r="189" fill="#deeeff" />
            <circle cx="467" cy="98" r="11" fill="#94caff" /><circle cx="83" cy="185" r="7" fill="#94caff" />
            <path d="M74 110h24m-12-12v24M456 255h20m-10-10v20" stroke="#79b7f8" strokeWidth="3" strokeLinecap="round" />
            <rect x="112" y="144" width="337" height="249" rx="10" fill="#fff" />
            <rect x="128" y="228" width="186" height="145" rx="5" fill="#e5f1ff" />
            <path d="M140 246h162v113H140z" fill="#c5e1ff" />
            <path d="M145 277h151M145 320h151" stroke="#fff" strokeWidth="7" />
            <path d="M152 253h22v19h-22zM183 247h18v25h-18zM213 253h26v19h-26zM249 248h17v24h-17zM275 257h14v15h-14z" fill="#6ca9ed" />
            <path d="M153 290h31v24h-31zM193 286h24v28h-24zM230 293h18v21h-18zM257 287h30v27h-30z" fill="#4189e3" />
            <path d="M153 332h24v21h-24zM188 337h18v16h-18zM218 330h27v23h-27zM257 333h31v20h-31z" fill="#8ebeff" />
            <rect x="329" y="228" width="102" height="165" rx="5" fill="#195bc3" />
            <rect x="340" y="241" width="80" height="109" rx="3" fill="#a8d1ff" />
            <path d="m347 249 32 94m-11-94 31 94" stroke="#e3f1ff" strokeWidth="9" opacity=".6" />
            <rect x="407" y="304" width="5" height="20" rx="2.5" fill="#fff" />
            <rect x="100" y="99" width="361" height="61" rx="10" fill="#175cd3" />
            <text x="280" y="138" fill="white" textAnchor="middle" fontFamily="Arial, sans-serif" fontWeight="800" fontSize="29" letterSpacing="5">TOKO MAWA</text>
            <path d="M112 163h337l25 46H88z" fill="#fff" />
            <path d="M112 163h47l-13 46H88zm94 0h47l-4 46h-52zm94 0h47l8 46h-54zm94 0h55l25 46h-65z" fill="#2c75dd" />
            <path d="M88 209h58v8a29 22 0 0 1-58 0zm109 0h52v8a26 22 0 0 1-52 0zm104 0h54v8a27 22 0 0 1-54 0zm108 0h65v8a32.5 22 0 0 1-65 0z" fill="#175cd3" />
            <path d="M146 209h51v8a25.5 22 0 0 1-51 0zm103 0h52v8a26 22 0 0 1-52 0zm106 0h54v8a27 22 0 0 1-54 0z" fill="#e8f2ff" />
            <rect x="100" y="388" width="361" height="13" rx="6" fill="#8ab7eb" />
            <path d="M61 382c-19-33-11-49-1-47 9 2 9 18 11 26 1-35 13-51 22-41 8 9-2 31-10 48 20-15 31-13 31-4 0 12-25 22-33 27" fill="#438bd7" />
            <path d="M56 379h43l-7 23H63z" fill="#123d83" />
            <rect x="433" y="330" width="57" height="68" rx="9" fill="#175cd3" transform="rotate(7 433 330)" />
            <path d="M446 338v-9a13 13 0 0 1 26 0v12" fill="none" stroke="#175cd3" strokeWidth="5" />
            <path d="m449 360 8 8 15-15" stroke="white" strokeWidth="4" fill="none" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export default function Welcome({ canLogin }) {
    const { auth, storeProfile = {} } = usePage().props;
    const signedIn = Boolean(auth?.user);
    const appRoute = can("dashboard-access", auth) ? "dashboard" : "dashboard.access";
    const phone = storeProfile.phone?.replace(/[^+\d]/g, "");
    const contactAvailable = Boolean(storeProfile.address || phone || storeProfile.email);
    const features = [
        { icon: IconShoppingBag, title: "Belanja di toko", text: "Datang langsung dan pilih produk sesuai kebutuhan Anda." },
        { icon: IconHeartHandshake, title: "Kami siap membantu", text: "Tanyakan produk yang Anda cari kepada tim Toko Mawa." },
        { icon: IconReceipt, title: "Transaksi tercatat", text: "Setiap pembelian dicatat melalui sistem kasir toko." },
    ];
    return (
        <>
            <Head title="Selamat datang" />
            <div className="min-h-screen bg-white text-slate-900 dark:bg-slate-950 dark:text-white">
                <header className="border-b border-slate-100 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
                        <Link href="/" aria-label="Beranda Toko Mawa"><StoreBrand subtitle="Teman belanja Anda" /></Link>
                        <nav aria-label="Navigasi utama" className="hidden items-center gap-8 text-sm font-medium text-slate-500 md:flex"><a href="#beranda" className="text-primary-600 dark:text-primary-300">Beranda</a><a href="#tentang" className="hover:text-primary-600">Tentang kami</a><a href="#kunjungi" className="hover:text-primary-600">Kunjungi toko</a></nav>
                        {canLogin && <Link href={signedIn ? route(appRoute) : route("login")} className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary-600 px-4 text-sm font-semibold text-white transition hover:bg-primary-700">{signedIn ? "Buka aplikasi" : "Masuk"}<IconArrowUpRight size={17} /></Link>}
                    </div>
                </header>
                <main>
                    <section id="beranda" className="relative overflow-hidden bg-[#f2f7ff] dark:bg-slate-900/50">
                        <div className="mx-auto grid max-w-7xl items-center gap-6 px-5 pb-10 pt-14 sm:px-8 sm:pt-20 lg:grid-cols-2 lg:pb-16">
                            <div className="relative z-10">
                                <span className="mb-7 inline-flex items-center gap-2 rounded-full border border-primary-100 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-200"><span className="h-1.5 w-1.5 rounded-full bg-primary-500" /> SELAMAT DATANG DI TOKO MAWA</span>
                                <h1 className="max-w-xl text-[2.7rem] font-extrabold leading-[1.14] tracking-tight sm:text-6xl">Belanja lebih nyaman,<br /><span className="text-primary-600 dark:text-primary-400">di Toko Mawa.</span></h1>
                                <p className="mt-6 max-w-md text-base leading-8 text-slate-500 dark:text-slate-400">Untuk kebutuhan Anda, kami siap membantu. Singgah ke Toko Mawa dan temukan apa yang Anda cari.</p>
                                <div className="mt-8 flex flex-wrap gap-3"><a href="#kunjungi" className="inline-flex min-h-12 items-center gap-3 rounded-xl bg-primary-600 px-6 text-sm font-semibold text-white transition hover:bg-primary-700">Kunjungi toko<IconArrowRight size={18} /></a><a href="#tentang" className="inline-flex min-h-12 items-center rounded-xl border border-slate-200 bg-white px-6 text-sm font-semibold text-slate-700 transition hover:border-primary-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Kenali Toko Mawa</a></div>
                                <div className="mt-9 flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400"><span className="flex h-9 w-9 items-center justify-center rounded-full border border-primary-100 bg-white text-primary-600 dark:border-slate-700 dark:bg-slate-800"><IconBuildingStore size={18} /></span><span>Toko Anda. Kebutuhan Anda.<br /><strong className="font-semibold text-slate-700 dark:text-slate-300">Kami senang menyambut Anda.</strong></span></div>
                            </div>
                            <div className="relative mx-auto w-full max-w-xl"><StoreIllustration /><div className="absolute bottom-6 left-4 flex items-center gap-3 rounded-2xl border border-white bg-white/95 px-5 py-4 shadow-xl shadow-blue-900/5 sm:bottom-10 sm:left-8 dark:border-slate-700 dark:bg-slate-900"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-900"><IconHeartHandshake size={23} /></span><div><p className="text-sm font-bold">Sampai jumpa di toko!</p><p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Salam hangat dari tim Toko Mawa</p></div></div></div>
                        </div>
                    </section>
                    <section id="tentang" className="mx-auto max-w-7xl scroll-mt-6 px-5 py-16 sm:px-8 sm:py-20">
                        <div className="mb-9 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400">Dekat dengan Anda</p><h2 className="text-3xl font-bold tracking-tight">Bukan sekadar tempat belanja.</h2></div><p className="max-w-sm text-sm leading-7 text-slate-500 dark:text-slate-400">Toko Mawa hadir untuk menemani kebutuhan Anda, dengan pelayanan yang ramah dan proses belanja yang sederhana.</p></div>
                        <div className="grid gap-5 md:grid-cols-3">{features.map(({ icon: Icon, title, text }, index) => <article key={title} className="relative rounded-2xl border border-slate-200 bg-white p-7 dark:border-slate-800 dark:bg-slate-900"><span className="absolute right-6 top-7 text-xs font-medium text-slate-300 dark:text-slate-600">0{index + 1}</span><span className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-900/40 dark:text-primary-300"><Icon size={25} strokeWidth={1.6} /></span><h3 className="text-lg font-bold">{title}</h3><p className="mt-3 text-sm leading-7 text-slate-500 dark:text-slate-400">{text}</p></article>)}</div>
                    </section>
                    <section id="kunjungi" className="mx-auto max-w-7xl scroll-mt-6 px-5 pb-16 sm:px-8 sm:pb-20">
                        <div className="grid gap-9 rounded-[2rem] bg-[#12377b] px-7 py-10 text-white sm:px-12 sm:py-12 md:grid-cols-2 md:items-center"><div><p className="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-blue-200">Mari singgah</p><h2 className="text-3xl font-bold leading-tight sm:text-4xl">Kami tunggu kehadiran<br />Anda di Toko Mawa.</h2><p className="mt-5 max-w-sm text-sm leading-7 text-blue-100/80">Ada yang ingin ditanyakan? Tim kami siap membantu Anda menemukan kebutuhan yang tepat.</p></div><div className="rounded-2xl border border-white/15 bg-white/5 p-6 sm:p-8"><h3 className="mb-5 flex items-center gap-3 text-lg font-bold"><IconBuildingStore size={23} />Toko Mawa</h3>{storeProfile.address && <p className="mb-4 flex items-start gap-3 text-sm leading-7 text-blue-100"><IconMapPin className="mt-1 shrink-0" size={19} /><span>{storeProfile.address}{storeProfile.city ? `, ${storeProfile.city}` : ""}</span></p>}{phone && <a href={`tel:${phone}`} className="mb-4 flex items-center gap-3 text-sm text-blue-100 hover:text-white"><IconPhone size={19} />{storeProfile.phone}</a>}{storeProfile.email && <a href={`mailto:${storeProfile.email}`} className="mb-4 flex items-center gap-3 break-all text-sm text-blue-100 hover:text-white"><IconMail size={19} />{storeProfile.email}</a>}{!contactAvailable && <p className="text-sm leading-7 text-blue-100">Datang dan sapa tim kami secara langsung di toko. Dengan senang hati kami membantu Anda.</p>}{phone && <a href={`tel:${phone}`} className="mt-3 inline-flex min-h-11 items-center gap-3 rounded-xl bg-white px-5 text-sm font-semibold text-primary-800 transition hover:bg-blue-100">Hubungi toko<IconArrowUpRight size={17} /></a>}</div></div>
                    </section>
                </main>
                <footer className="border-t border-slate-100 dark:border-slate-800"><div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5 px-5 py-7 text-xs text-slate-500 sm:px-8"><StoreBrand subtitle="Teman belanja Anda" /><span>© {new Date().getFullYear()} Toko Mawa</span>{canLogin && <Link href={signedIn ? route(appRoute) : route("login")} className="font-medium hover:text-primary-600">{signedIn ? "Kembali ke aplikasi" : "Akses karyawan"}</Link>}</div></footer>
            </div>
        </>
    );
}
