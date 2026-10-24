import { usePage } from "@inertiajs/react";

export default function StoreBrand({ compact = false, hideLabelOnMobile = false, light = false, subtitle = "Sistem operasional toko" }) {
    const { storeProfile } = usePage().props;
    return (
        <span className="inline-flex min-w-0 items-center gap-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-primary-700 text-white">
                {storeProfile?.logo ? (
                    <img src={storeProfile.logo} alt="" className="h-full w-full object-contain" />
                ) : (
                    <svg viewBox="0 0 40 40" className="h-8 w-8" fill="none" aria-hidden="true">
                        <path d="M9 29V13l11 11 11-11v16" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round" />
                        <path d="M9 8h22" stroke="#8dcdff" strokeWidth="3" strokeLinecap="round" />
                    </svg>
                )}
            </span>
            {!compact && (
                <span className={`min-w-0 text-left ${hideLabelOnMobile ? "hidden sm:block" : ""}`}>
                    <span className={`block truncate text-lg font-extrabold tracking-tight ${light ? "text-white" : "text-slate-900 dark:text-white"}`}>Toko Mawa</span>
                    <span className={`block truncate text-[10px] font-medium tracking-wide ${light ? "text-blue-100" : "text-slate-500 dark:text-slate-400"}`}>{subtitle}</span>
                </span>
            )}
        </span>
    );
}
