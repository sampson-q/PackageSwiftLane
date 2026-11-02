"use strict";

/* ---------------------------------------------------------------------------
   Starter templates for the e-mail newsletter builder (dataJs/newsletter.js).

   NLB_TEMPLATES(cfg, mk) returns the list shown on the "Choose Template" step.
   mk(type, props) makes a block with that type's defaults and the given props;
   cfg is window.NLB_CONFIG (site name, logo path...). Images point at the
   placeholder pictures in assets/images/newsletter/ (made for these templates);
   the sender replaces them with their own from the block settings.
   --------------------------------------------------------------------------- */

window.NLB_TEMPLATES = function (cfg, mk) {
    var IMG = 'assets/images/newsletter/';
    var logo = function (p) { return mk('logo', Object.assign({ src: cfg.logo || '' }, p || {})); };
    var footer = function (p) {
        return mk('footer', Object.assign({
            html: '<p><b>[SITE_NAME]</b><br>You are receiving this e-mail because you have an account with [SITE_NAME].</p>' +
                  '<p><a href="[URL]">Visit our website</a> &nbsp;·&nbsp; <a href="[UNSUBSCRIBE_URL]">Unsubscribe</a></p>' +
                  '<p>&copy; [YEAR] [SITE_NAME]. All rights reserved.</p>'
        }, p || {}));
    };
    var social = function (p) {
        return mk('social', Object.assign({
            links: [
                { net: 'facebook', url: 'https://facebook.com/' },
                { net: 'instagram', url: 'https://instagram.com/' },
                { net: 'x', url: 'https://x.com/' },
                { net: 'whatsapp', url: 'https://wa.me/' }
            ]
        }, p || {}));
    };

    return [
        // 1 ────────────────────────────────────────────────────────────────
        {
            id: 'monthly',
            name: 'Monthly Newsletter',
            description: 'Logo, hero picture, a story and two news cards.',
            subject: '[SITE_NAME] news: what moved this month',
            preheader: 'New routes, better rates and tips for shipping smarter.',
            design: {
                settings: { bodyBg: '#EEF1F5', contentBg: '#FFFFFF', font: 'Arial, Helvetica, sans-serif', textColor: '#3D4B5C', headingColor: '#192A3E', linkColor: '#0077B6', accent: '#FFCB01' },
                blocks: [
                    logo({ bg: '#192A3E', pt: 20, pb: 20 }),
                    mk('image', { src: IMG + 'hero-shipping.jpg', alt: 'This month at [SITE_NAME]' }),
                    mk('heading', { text: 'Hello [FIRST_NAME], here is what moved this month', size: 26, pt: 32 }),
                    mk('text', { html: '<p>Thank you for shipping with us. This month we opened new weekly departures, cut clearance times at the port and added Saturday collections in more areas.</p><p>Read on for the highlights, and as always, reply to this e-mail if there is anything we can help with.</p>' }),
                    mk('button', { label: 'Track a shipment', href: '[TRACK_URL]', pt: 16, pb: 24 }),
                    mk('divider', {}),
                    mk('columns', {
                        cols: [
                            { img: true, src: IMG + 'card-air.jpg', title: 'Faster air freight', html: '<p>Twice-weekly departures mean your parcels leave sooner, with tracking at every step.</p>', btn: 'Read more', href: '[URL]' },
                            { img: true, src: IMG + 'card-sea.jpg', title: 'Sea freight savings', html: '<p>Consolidate larger orders by sea and pay far less per kilo on heavy items.</p>', btn: 'Get a quote', href: '[URL]' }
                        ]
                    }),
                    mk('spacer', { height: 16 }),
                    social({ bg: '#F6F7F9', pt: 24, pb: 8 }),
                    footer({ bg: '#F6F7F9' })
                ]
            }
        },

        // 2 ────────────────────────────────────────────────────────────────
        {
            id: 'promo',
            name: 'Promotion / Sale',
            description: 'Bold banner, offer code and three reasons to ship now.',
            subject: 'Save on your next shipment, [FIRST_NAME]',
            preheader: 'Our seasonal rates are live for a limited time only.',
            design: {
                settings: { bodyBg: '#FFF4E0', contentBg: '#FFFFFF', font: 'Verdana, Geneva, sans-serif', textColor: '#3D4B5C', headingColor: '#192A3E', linkColor: '#D80613', accent: '#D80613' },
                blocks: [
                    logo({ pt: 20, pb: 12 }),
                    mk('image', { src: IMG + 'hero-promo.jpg', alt: 'Seasonal savings', href: '[URL]' }),
                    mk('heading', { text: 'Seasonal rates are here', size: 32, align: 'center', pt: 32 }),
                    mk('text', { html: '<p>For a limited time, ship more for less on air and sea freight. Book before the end of the month and use the code below at the counter or online.</p>', align: 'center' }),
                    mk('heading', { text: 'SHIPSMART', size: 30, align: 'center', color: '#D80613', bg: '#FFF4E0', pt: 18, pb: 18 }),
                    mk('button', { label: 'Book my shipment', href: '[URL]', color: '#D80613', textColor: '#FFFFFF', size: 18, radius: 30, pt: 24, pb: 24 }),
                    mk('columns', {
                        align: 'center',
                        cols: [
                            { img: false, src: '', title: 'Weekly departures', html: '<p>Regular flights and sailings, so nothing waits.</p>', btn: '', href: '' },
                            { img: false, src: '', title: 'Live tracking', html: '<p>Follow every parcel from pickup to your door.</p>', btn: '', href: '' },
                            { img: false, src: '', title: 'Door delivery', html: '<p>We bring it to you, wherever you are.</p>', btn: '', href: '' }
                        ],
                        bg: '#F6F7F9', pt: 24, pb: 24
                    }),
                    mk('text', { html: '<p><i>Offer valid until the end of the month on new bookings only. Cannot be combined with other discounts.</i></p>', size: 12, align: 'center', color: '#8392A5' }),
                    social({}),
                    footer({})
                ]
            }
        },

        // 3 ────────────────────────────────────────────────────────────────
        {
            id: 'announcement',
            name: 'Service Announcement',
            description: 'A clear update with a picture beside the details.',
            subject: 'Important update about your deliveries',
            preheader: 'A quick note on what is changing and what you need to do.',
            design: {
                settings: { bodyBg: '#F6F7F9', contentBg: '#FFFFFF', font: 'Helvetica, Arial, sans-serif', textColor: '#3D4B5C', headingColor: '#192A3E', linkColor: '#0077B6', accent: '#192A3E' },
                blocks: [
                    logo({ align: 'left', pt: 24, pb: 8 }),
                    mk('divider', { color: '#FFCB01', thickness: 4, pt: 8, pb: 8 }),
                    mk('heading', { text: 'An update on our service', size: 28, pt: 24 }),
                    mk('text', { html: '<p>Dear [NAME],</p><p>We are making a change to how and when parcels are collected from our warehouse. Here is what you need to know.</p>' }),
                    mk('imagetext', { src: IMG + 'card-warehouse.jpg', side: 'left', title: 'New collection hours', html: '<p>From the first of next month our warehouse is open <b>Monday to Saturday, 8:00 to 18:00</b>. Bring your ID and pickup code.</p>', btn: 'See locations', href: '[URL]' }),
                    mk('imagetext', { src: IMG + 'card-delivery.jpg', side: 'right', title: 'Door delivery is now free', html: '<p>Orders over the minimum weight are now delivered to your door at no extra cost.</p>', btn: '', href: '' }),
                    mk('text', { html: '<p>Questions? Just reply to this e-mail and our team will help.</p><p>Thank you,<br>The [SITE_NAME] team</p>', pt: 16, pb: 24 }),
                    mk('button', { label: 'Track your shipment', href: '[TRACK_URL]', align: 'left', color: '#192A3E', textColor: '#FFFFFF', pb: 32 }),
                    footer({ bg: '#192A3E', color: '#C9D2DD' })
                ]
            }
        },

        // 4 ────────────────────────────────────────────────────────────────
        {
            id: 'welcome',
            name: 'Welcome Email',
            description: 'Greets new customers and shows them how to start.',
            subject: 'Welcome to [SITE_NAME], [FIRST_NAME]!',
            preheader: 'Your account is ready. Here is how to send your first parcel.',
            design: {
                settings: { bodyBg: '#E8F4FA', contentBg: '#FFFFFF', font: 'Trebuchet MS, Helvetica, sans-serif', textColor: '#3D4B5C', headingColor: '#005785', linkColor: '#0077B6', accent: '#FFCB01' },
                blocks: [
                    logo({ pt: 24, pb: 16 }),
                    mk('image', { src: IMG + 'hero-welcome.jpg', alt: 'Welcome to [SITE_NAME]', px: 24, radius: 12 }),
                    mk('heading', { text: 'Welcome aboard, [FIRST_NAME]!', size: 30, align: 'center', pt: 28 }),
                    mk('text', { html: '<p>We are glad you are here. Your account is ready, and shipping with us takes three simple steps.</p>', align: 'center' }),
                    mk('columns', {
                        align: 'center',
                        cols: [
                            { img: false, src: '', title: '1. Get your address', html: '<p>Use your personal shipping address when you shop online.</p>', btn: '', href: '' },
                            { img: false, src: '', title: '2. Pre-alert us', html: '<p>Tell us what is coming so we can receive it quickly.</p>', btn: '', href: '' },
                            { img: false, src: '', title: '3. Track & collect', html: '<p>Follow it home and collect, or have it delivered.</p>', btn: '', href: '' }
                        ],
                        pt: 16, pb: 16
                    }),
                    mk('button', { label: 'Go to my account', href: '[URL]', size: 18, pt: 16, pb: 32 }),
                    mk('divider', {}),
                    mk('text', { html: '<p>Need a hand? Our team is one reply away.</p>', align: 'center', size: 14 }),
                    social({}),
                    footer({})
                ]
            }
        },

        // 5 ────────────────────────────────────────────────────────────────
        {
            id: 'video',
            name: 'Video Spotlight',
            description: 'Leads with a video, then a feature and a call to action.',
            subject: 'See how your parcel travels with [SITE_NAME]',
            preheader: 'Two minutes behind the scenes at our warehouse.',
            design: {
                settings: { bodyBg: '#192A3E', contentBg: '#FFFFFF', font: 'Arial, Helvetica, sans-serif', textColor: '#3D4B5C', headingColor: '#192A3E', linkColor: '#5548D1', accent: '#FFCB01' },
                blocks: [
                    logo({ bg: '#192A3E', pt: 24, pb: 24 }),
                    mk('heading', { text: 'Behind the scenes', size: 30, align: 'center', pt: 32 }),
                    mk('text', { html: '<p>Ever wondered what happens after you drop off a parcel? Watch our short video to see it travel from our warehouse to your door.</p>', align: 'center' }),
                    mk('video', { url: 'https://www.youtube.com/', thumb: IMG + 'video-thumb.jpg', caption: 'Watch the video (2 min)' }),
                    mk('imagetext', { src: IMG + 'card-air.jpg', side: 'right', title: 'Packed with care', html: '<p>Every parcel is weighed, photographed and sealed before it leaves, so you always know it is safe.</p>', btn: 'Learn more', href: '[URL]' }),
                    mk('button', { label: 'Start shipping', href: '[URL]', pt: 24, pb: 32 }),
                    footer({ bg: '#F6F7F9' })
                ]
            }
        },

        // 6 ────────────────────────────────────────────────────────────────
        {
            id: 'holiday',
            name: 'Holiday Notice',
            description: 'Seasonal greeting with opening hours and cut-off dates.',
            subject: 'Holiday hours and shipping cut-off dates',
            preheader: 'Plan ahead so your parcels arrive in time.',
            design: {
                settings: { bodyBg: '#F3EFFC', contentBg: '#FFFFFF', font: 'Georgia, Times New Roman, serif', textColor: '#3D4B5C', headingColor: '#162536', linkColor: '#5548D1', accent: '#D80613' },
                blocks: [
                    mk('image', { src: IMG + 'hero-holiday.jpg', alt: 'Season\'s greetings from [SITE_NAME]' }),
                    mk('heading', { text: 'Season\'s greetings, [FIRST_NAME]', size: 28, align: 'center', pt: 32 }),
                    mk('text', { html: '<p>Thank you for a wonderful year. To help your parcels arrive in time for the holidays, please note our cut-off dates and opening hours below.</p>', align: 'center' }),
                    mk('divider', { pt: 8, pb: 8 }),
                    mk('columns', {
                        cols: [
                            { img: false, src: '', title: 'Last booking dates', html: '<p>Air freight: <b>12 December</b><br>Sea freight: <b>20 November</b></p>', btn: '', href: '' },
                            { img: false, src: '', title: 'Opening hours', html: '<p>24 Dec: 8:00 to 13:00<br>25 to 26 Dec: closed<br>31 Dec: 8:00 to 13:00<br>1 Jan: closed</p>', btn: '', href: '' }
                        ],
                        bg: '#F6F7F9', pt: 24, pb: 24
                    }),
                    mk('button', { label: 'Book before the cut-off', href: '[URL]', color: '#D80613', textColor: '#FFFFFF', pt: 24, pb: 24 }),
                    mk('text', { html: '<p>Warm wishes from all of us,<br><b>The [SITE_NAME] team</b></p>', align: 'center' }),
                    social({ pt: 16 }),
                    footer({})
                ]
            }
        },

        // Blank ─────────────────────────────────────────────────────────────
        {
            id: 'blank',
            name: 'Blank',
            description: 'Start from an empty e-mail with just a footer.',
            subject: '',
            preheader: '',
            design: {
                settings: { bodyBg: '#F6F7F9', contentBg: '#FFFFFF', font: 'Arial, Helvetica, sans-serif', textColor: '#3D4B5C', headingColor: '#192A3E', linkColor: '#0077B6', accent: '#FFCB01' },
                blocks: [
                    logo({}),
                    footer({})
                ]
            }
        }
    ];
};
