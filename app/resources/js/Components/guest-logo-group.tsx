export default function GuestLogoGroup() {
    return (
        <div className="pgs-guest-card-logo" aria-label="PGS organization logos">
            <img
                src="/guest-assets/logo_doh2.png"
                alt="Department of Health seal"
                className="pgs-guest-logo-seal"
            />
            <img
                src="/guest-assets/pgs_logo.png"
                alt="Performance Governance System logo"
                className="pgs-guest-logo-pgs"
            />
            <img
                src="/guest-assets/logo_trc.png"
                alt="Treatment and Rehabilitation Center seal"
                className="pgs-guest-logo-seal"
            />
        </div>
    );
}
