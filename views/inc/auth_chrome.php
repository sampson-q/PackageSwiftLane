<?php
/**
 * Shared frame for the public/auth pages (login, register, OTP, forgot/reset,
 * tracking, terms): a full-bleed shipping photograph behind the page, a top bar
 * with the brand and page links, a centred stage that holds the page's card, and
 * a footer. The page keeps its own card markup and calls:
 *
 *   cdp_authFrameOpen($core, [
 *       'photo' => 'login',                 // assets/images/auth/<photo>.{jpg,webp}
 *       'badge' => 'Swift Lane Logistics',  // small amber pill above the card
 *       'title' => 'Freight that moves…',   // white headline above the card
 *       'links' => [['href' => 'tracking.php', 'label' => 'Track a Parcel', 'icon' => 'send']],
 *       'wide'  => false,                   // true for the register form
 *   ]);
 *   …card…
 *   cdp_authFrameClose($core);
 *
 * Photos are free-licence Unsplash images; sources are listed in
 * assets/images/auth/README.md.
 */

if (!function_exists('cdp_authFrameOpen')) {

    function cdp_authFrameEsc($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    /** The photo used when a page asks for one that is not on disk. */
    function cdp_authPhotoName(?string $photo): string
    {
        $photo = preg_replace('/[^a-z0-9_-]/i', '', (string) $photo);
        $known = ['login', 'signup', 'otp', 'forgot', 'tracking', 'terms'];
        return in_array($photo, $known, true) ? $photo : 'login';
    }

    /**
     * <link rel=preload> tags for the page photo, for the <head>. The photo is a
     * CSS background, which the browser would otherwise only discover after the
     * stylesheet has been parsed.
     */
    function cdp_authPhotoPreload(?string $photo): void
    {
        $p = cdp_authPhotoName($photo);
        echo '<link rel="preload" as="image" type="image/webp" href="assets/images/auth/' . $p . '-sm.webp" media="(max-width: 900px)">' . "\n";
        echo '    <link rel="preload" as="image" type="image/webp" href="assets/images/auth/' . $p . '.webp" media="(min-width: 901px)">' . "\n";
    }

    function cdp_authBrandMarkup($core): string
    {
        $name = cdp_authFrameEsc($core->site_name ?? '');
        if (!empty($core->logo_web)) {
            return '<img src="assets/' . cdp_authFrameEsc($core->logo_web) . '" alt="' . $name . '">';
        }
        return '<strong>' . $name . '</strong>';
    }

    function cdp_authFrameOpen($core, array $o = []): void
    {
        $photo = cdp_authPhotoName($o['photo'] ?? 'login');
        $links = $o['links'] ?? [];
        $wide  = !empty($o['wide']);
        ?>
    <div class="auth-photo auth-photo--<?php echo $photo; ?>" aria-hidden="true"></div>

    <header class="auth-topbar">
        <a class="auth-brand" href="index.php" aria-label="<?php echo cdp_authFrameEsc($core->site_name ?? 'Home'); ?>">
            <?php echo cdp_authBrandMarkup($core); ?>
        </a>
        <?php if ($links) { ?>
            <nav class="auth-topbar__nav" aria-label="Page links">
                <?php foreach ($links as $l) { ?>
                    <a class="auth-topbar__link<?php echo !empty($l['primary']) ? ' is-primary' : ''; ?>" href="<?php echo cdp_authFrameEsc($l['href']); ?>">
                        <?php if (!empty($l['icon'])) { ?><i data-feather="<?php echo cdp_authFrameEsc($l['icon']); ?>" class="icons"></i><?php } ?>
                        <span><?php echo cdp_authFrameEsc($l['label']); ?></span>
                    </a>
                <?php } ?>
            </nav>
        <?php } ?>
    </header>

    <main class="auth-stage<?php echo $wide ? ' auth-stage--wide' : ''; ?>">
        <?php if (!empty($o['badge']) || !empty($o['title'])) { ?>
            <div class="auth-intro">
                <?php if (!empty($o['badge'])) { ?><span class="auth-badge"><?php echo cdp_authFrameEsc($o['badge']); ?></span><?php } ?>
                <?php if (!empty($o['title'])) { ?><p class="auth-intro__title"><?php echo cdp_authFrameEsc($o['title']); ?></p><?php } ?>
            </div>
        <?php } ?>
        <?php
    }

    function cdp_authFrameClose($core): void
    {
        ?>
    </main>

    <footer class="auth-foot">
        <span>&copy; <?php echo date('Y'); ?> <?php echo cdp_authFrameEsc($core->site_name ?? ''); ?></span>
        <span class="auth-foot__sep" aria-hidden="true">&middot;</span>
        <span>Air &amp; Sea Freight</span>
        <span class="auth-foot__sep" aria-hidden="true">&middot;</span>
        <a href="terms.php">Terms &amp; Conditions</a>
    </footer>
        <?php
    }
}
