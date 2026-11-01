<?php
/**
 * Seed data: services, packages (indicative prices – editable in admin), FAQs.
 * Tier definition: 'basic' carries the full feature list, higher tiers list only additions.
 */

defined( 'ABSPATH' ) || exit;

$tier = static function ( array $price, array $f, string $rev, string $del, string $sup, string $note = '', ?array $sale = null ): array {
	return array( 'price' => $price[0], 'f' => $f, 'rev' => $rev, 'del' => $del, 'sup' => $sup, 'note' => $note );
};

$proc = array(
	'Discover | We learn your goals, audience and competitors.',
	'Plan | A clear scope, timeline and milestones you approve.',
	'Build | Our team delivers with regular progress updates.',
	'Launch & support | Go live, hand-over and ongoing assistance.',
);

return array(
	'services' => array(
		array(
			'slug' => 'website-design', 'title' => 'Website Design', 'icon' => 'design', 'billing' => 'one_time',
			'tagline' => 'Beautiful, conversion-focused website designs that reflect your brand.',
			'body' => 'Your website is your digital storefront. We design modern, mobile-first layouts that look premium, load fast and guide visitors towards enquiries and purchases — before a single line of code is written.',
			'highlights' => array( 'Mobile-first, responsive layouts', 'Brand-aligned colours & typography', 'Conversion-focused page structure', 'Source design files included' ),
			'process' => $proc,
			'addons' => array( array( 'label' => 'Extra page design', 'price' => 1500, 'recurring' => 0 ), array( 'label' => 'Brand style guide', 'price' => 3000, 'recurring' => 0 ) ),
			'tiers' => array(
				'basic' => $tier( array( 7999 ), array( 'Up to 5 page designs', 'Mobile & desktop layouts', 'Brand colour & font selection', 'Source files (Figma)' ), '2 revisions', '5–7 days', '15 days' ),
				'professional' => $tier( array( 17999 ), array( 'Up to 10 page designs', 'Interactive prototype', 'Custom icon & illustration set', 'Accessibility review' ), '4 revisions', '10–14 days', '30 days' ),
				'enterprise' => $tier( array( 34999 ), array( 'Up to 20 page designs', 'Design system & components', 'Stakeholder workshop', 'Dedicated designer' ), 'Unlimited revisions', '3–4 weeks', '90 days', '+' ),
			),
		),
		array(
			'slug' => 'website-development', 'title' => 'Website Development', 'icon' => 'code', 'billing' => 'one_time',
			'tagline' => 'Fast, secure, SEO-ready websites built on WordPress and modern web technology.',
			'body' => 'From business websites to content portals, we build clean, scalable sites that are easy for you to manage. Every build includes responsive design, speed optimisation, on-page SEO foundations and security hardening.',
			'highlights' => array( 'Custom WordPress development', 'Responsive on every device', 'Speed, security & SEO foundations', 'Easy-to-use admin dashboard' ),
			'process' => $proc,
			'addons' => array( array( 'label' => 'Extra page', 'price' => 1000, 'recurring' => 0 ), array( 'label' => 'Blog setup & 3 starter posts', 'price' => 2500, 'recurring' => 0 ), array( 'label' => 'WhatsApp & live chat integration', 'price' => 1500, 'recurring' => 0 ) ),
			'tiers' => array(
				'basic' => $tier( array( 9999 ), array( 'Up to 5 pages', 'Responsive design', 'Contact form & WhatsApp button', 'Basic on-page SEO', 'SSL & security setup' ), '2 revisions', '7–10 days', '1 month' ),
				'professional' => $tier( array( 24999 ), array( 'Up to 12 pages', 'Custom theme design', 'Blog & CMS training', 'Speed optimisation', 'Google Analytics & Search Console' ), '4 revisions', '2–3 weeks', '3 months' ),
				'enterprise' => $tier( array( 49999 ), array( 'Up to 25 pages', 'Custom features & integrations', 'Multilingual ready', 'Advanced SEO & schema markup', 'Priority support' ), 'Unlimited revisions', '4–6 weeks', '6 months', '+' ),
			),
		),
		array(
			'slug' => 'ecommerce-development', 'title' => 'E-commerce Development', 'icon' => 'cart', 'billing' => 'one_time',
			'tagline' => 'Online stores with secure payments, inventory and shipping built for Indian businesses.',
			'body' => 'Sell online with a WooCommerce store tailored to your catalogue. We integrate Razorpay and other Indian payment gateways, GST-ready invoicing, shipping and order management so you can start selling with confidence.',
			'highlights' => array( 'WooCommerce store setup', 'Razorpay / UPI / cards / net banking', 'GST-ready invoices', 'Inventory & order management' ),
			'process' => $proc,
			'addons' => array( array( 'label' => 'Additional payment gateway', 'price' => 3000, 'recurring' => 0 ), array( 'label' => 'Product upload (per 50 products)', 'price' => 2500, 'recurring' => 0 ) ),
			'tiers' => array(
				'basic' => $tier( array( 24999 ), array( 'Up to 50 products', 'Razorpay payment integration', 'Cart, checkout & coupons', 'GST configuration', 'Mobile-friendly storefront' ), '3 revisions', '2–3 weeks', '2 months' ),
				'professional' => $tier( array( 54999 ), array( 'Up to 500 products', 'Shipping-partner integration', 'Customer accounts & wishlist', 'Abandoned-cart emails', 'Advanced product filters' ), '5 revisions', '4–5 weeks', '4 months' ),
				'enterprise' => $tier( array( 99999 ), array( 'Unlimited products', 'ERP / CRM integration', 'Multi-vendor or B2B pricing', 'Performance & load testing', 'Dedicated project manager' ), 'Unlimited revisions', '8–10 weeks', '12 months', '+' ),
			),
		),
		array(
			'slug' => 'custom-web-application-development', 'title' => 'Custom Web Application Development', 'icon' => 'rocket', 'billing' => 'one_time',
			'tagline' => 'Portals, dashboards and SaaS products engineered around your workflow.',
			'body' => 'When off-the-shelf tools do not fit, we build custom web applications — customer portals, booking systems, dashboards and internal tools — with clean architecture, secure APIs and scalable infrastructure.',
			'highlights' => array( 'Requirement workshops & scoping', 'Secure REST APIs', 'Role-based access control', 'Cloud deployment & CI/CD' ),
			'process' => $proc,
			'addons' => array( array( 'label' => 'Admin analytics dashboard', 'price' => 15000, 'recurring' => 0 ) ),
			'tiers' => array(
				'basic' => $tier( array( 59999 ), array( 'Up to 5 modules', 'User roles & authentication', 'Admin dashboard', 'Deployment & documentation' ), '3 revisions', '6–8 weeks', '3 months' ),
				'professional' => $tier( array( 149999 ), array( 'Up to 12 modules', 'Third-party API integrations', 'Automated testing', 'Performance optimisation' ), '5 revisions', '10–14 weeks', '6 months' ),
				'enterprise' => array( 'price' => 0, 'f' => array( 'Unlimited scope', 'Dedicated team', 'SLA-backed support', 'Architecture & security review' ), 'rev' => 'As agreed', 'del' => 'Per scope', 'sup' => '12 months', 'note' => '', 'billing' => 'custom' ),
			),
		),
		array(
			'slug' => 'logo-design', 'title' => 'Logo & Graphic Design', 'icon' => 'logo', 'billing' => 'one_time',
			'tagline' => 'Memorable logos and brand graphics that make a lasting first impression.',
			'body' => 'A great logo is simple, versatile and unmistakably yours. We create original concepts, refine them with your feedback and deliver complete file packs for web, print and social media.',
			'highlights' => array( 'Original, custom concepts', 'Print & web file formats', 'Social media kit', 'Full copyright transfer' ),
			'process' => $proc,
			'addons' => array( array( 'label' => 'Business card design', 'price' => 799, 'recurring' => 0 ), array( 'label' => 'Letterhead & stationery', 'price' => 1499, 'recurring' => 0 ) ),
			'tiers' => array(
				'basic' => $tier( array( 1499 ), array( '2 logo concepts', 'PNG & JPG files', 'Transparent background' ), '2 revisions', '3 days', '7 days' ),
				'professional' => $tier( array( 3499 ), array( '4 logo concepts', 'Vector source files (AI/SVG/PDF)', 'Social media profile kit', 'Basic brand colour palette' ), '5 revisions', '5 days', '15 days' ),
				'enterprise' => $tier( array( 6999 ), array( '6 logo concepts', 'Complete brand guidelines', 'Stationery design set', 'Animated logo' ), 'Unlimited revisions', '10 days', '30 days' ),
			),
		),
		array(
			'slug' => 'ui-ux-design', 'title' => 'UI/UX Design', 'icon' => 'design', 'billing' => 'one_time',
			'tagline' => 'Research-backed interfaces that are intuitive, accessible and delightful.',
			'body' => 'We map user journeys, wireframe flows and craft polished interfaces for web and mobile products. Our designs are validated with real users and handed over with developer-ready specifications.',
			'highlights' => array( 'User research & journey mapping', 'Wireframes & prototypes', 'Usability testing', 'Developer hand-off' ),
			'process' => $proc,
			'addons' => array( array( 'label' => 'Usability test round (5 users)', 'price' => 8000, 'recurring' => 0 ) ),
			'tiers' => array(
				'basic' => $tier( array( 14999 ), array( 'Up to 8 screens', 'Low-fidelity wireframes', 'Clickable prototype' ), '2 revisions', '1–2 weeks', '15 days' ),
				'professional' => $tier( array( 34999 ), array( 'Up to 20 screens', 'High-fidelity UI design', 'User flows & journey map', 'Design system starter' ), '4 revisions', '3–4 weeks', '45 days' ),
				'enterprise' => $tier( array( 69999 ), array( 'Up to 40 screens', 'User research & testing', 'Full design system', 'Accessibility audit (WCAG)' ), 'Unlimited revisions', '6–8 weeks', '90 days', '+' ),
			),
		),
		array(
			'slug' => 'seo-services', 'title' => 'Search Engine Optimization (SEO)', 'icon' => 'seo', 'billing' => 'monthly',
			'tagline' => 'Rank higher, earn qualified traffic and grow organic leads month after month.',
			'body' => 'Our SEO programmes combine technical audits, keyword strategy, on-page optimisation, content and ethical link building. You get transparent monthly reports tied to rankings, traffic and leads — not vanity metrics.',
			'highlights' => array( 'Technical SEO audit & fixes', 'Keyword & competitor research', 'On-page & content optimisation', 'Monthly performance reports' ),
			'process' => array( 'Audit | Technical, content and competitor analysis.', 'Strategy | Keyword map and 90-day roadmap.', 'Execute | On-page, content and link building.', 'Report | Monthly results and next steps.' ),
			'addons' => array( array( 'label' => 'Extra 5 keywords', 'price' => 1500, 'recurring' => 1 ), array( 'label' => 'Google Business Profile optimisation', 'price' => 2500, 'recurring' => 0 ) ),
			'tiers' => array(
				'basic' => $tier( array( 4999 ), array( 'Up to 10 keywords', 'Technical SEO audit', 'On-page optimisation (5 pages)', 'Monthly report' ), 'Monthly review', 'Results in 3–6 months', 'Email support' ),
				'professional' => $tier( array( 9999 ), array( 'Up to 25 keywords', '4 blog articles per month', 'Link building (8 links)', 'Local SEO & Google Business Profile' ), 'Monthly review', 'Results in 3–6 months', 'Email & call support' ),
				'enterprise' => $tier( array( 19999 ), array( 'Up to 60 keywords', '8 articles per month', 'Link building (20 links)', 'Conversion-rate optimisation', 'Dedicated SEO manager' ), 'Weekly review', 'Results in 3–6 months', 'Priority support' ),
			),
		),
		array(
			'slug' => 'domain-registration', 'title' => 'Domain Registration', 'icon' => 'domain', 'billing' => 'yearly',
			'tagline' => 'Secure the perfect domain name for your brand — with setup handled for you.',
			'body' => 'We help you pick, register and configure your domain (.com, .in, .co.in and more) including DNS, email routing and renewal reminders, so your brand is protected from day one.',
			'highlights' => array( 'Domain name research', 'DNS & email configuration', 'Renewal reminders', 'Transfer assistance' ),
			'process' => array( 'Search | We shortlist available names.', 'Register | Domain registered in your name.', 'Configure | DNS, email and website pointing.', 'Protect | Renewal tracking and privacy.' ),
			'addons' => array( array( 'label' => 'Professional email (5 mailboxes)', 'price' => 2999, 'recurring' => 1 ) ),
			'tiers' => array(
				'basic' => $tier( array( 799 ), array( '.in / .com registration service', 'DNS configuration', 'Renewal reminders' ), 'n/a', 'Same day', 'Email support' ),
				'professional' => $tier( array( 1499 ), array( 'Premium TLDs (.co, .io, .tech)', 'WHOIS privacy protection', 'Email forwarding setup', 'DNS configuration' ), 'n/a', 'Same day', 'Email & call support' ),
				'enterprise' => $tier( array( 2999 ), array( 'Multiple domain portfolio (up to 5)', 'Brand protection monitoring', 'Domain transfer & migration', 'DNS configuration' ), 'n/a', '1–2 days', 'Priority support' ),
			),
		),
		array(
			'slug' => 'web-hosting', 'title' => 'Web Hosting', 'icon' => 'hosting', 'billing' => 'yearly',
			'tagline' => 'Fast, secure, monitored hosting tuned for WordPress and WooCommerce.',
			'body' => 'Reliable hosting with SSL, daily backups, malware scanning and uptime monitoring. We handle server setup, migrations and updates so you can focus on your business.',
			'highlights' => array( 'SSD storage & free SSL', 'Daily backups', 'Malware scanning', 'Free migration' ),
			'process' => array( 'Select | We size the plan to your traffic.', 'Migrate | Zero-downtime site migration.', 'Secure | SSL, firewall and backups.', 'Monitor | 24x7 uptime monitoring.' ),
			'addons' => array( array( 'label' => 'Extra daily backup retention (30 days)', 'price' => 1500, 'recurring' => 1 ) ),
			'tiers' => array(
				'basic' => $tier( array( 2999 ), array( '1 website', '10 GB SSD storage', 'Free SSL certificate', 'Daily backups' ), 'n/a', 'Same day setup', 'Email support' ),
				'professional' => $tier( array( 7999 ), array( '5 websites', '50 GB SSD storage', 'Free SSL certificate', 'Daily backups', 'Staging environment' ), 'n/a', 'Same day setup', 'Priority support' ),
				'enterprise' => $tier( array( 19999 ), array( 'Managed cloud / VPS', '200 GB SSD storage', 'Free SSL certificate', 'Daily backups', 'Dedicated resources & CDN' ), 'n/a', '1–2 days setup', '24x7 support' ),
			),
		),
		array(
			'slug' => 'social-media-marketing', 'title' => 'Social Media Marketing', 'icon' => 'social', 'billing' => 'monthly',
			'tagline' => 'Build your community, engage your audience and turn followers into customers.',
			'body' => 'We plan, design and publish scroll-stopping content across Instagram, Facebook, LinkedIn and YouTube — with community management and monthly analytics so you always know what is working.',
			'highlights' => array( 'Monthly content calendar', 'Custom post & reel design', 'Community management', 'Monthly analytics report' ),
			'process' => array( 'Strategy | Audience, pillars and calendar.', 'Create | Designs, copy and reels.', 'Publish | Scheduled posting and engagement.', 'Analyse | Insights and optimisation.' ),
			'addons' => array( array( 'label' => 'Extra platform', 'price' => 2000, 'recurring' => 1 ), array( 'label' => 'Reel video editing (4 per month)', 'price' => 3000, 'recurring' => 1 ) ),
			'tiers' => array(
				'basic' => $tier( array( 4999 ), array( '2 platforms', '12 designed posts per month', 'Content calendar', 'Monthly report' ), 'Monthly review', 'Starts in 3 days', 'Email support' ),
				'professional' => $tier( array( 9999 ), array( '3 platforms', '20 posts + 4 reels per month', 'Community management', 'Hashtag & competitor research' ), 'Monthly review', 'Starts in 3 days', 'Email & call support' ),
				'enterprise' => $tier( array( 19999 ), array( '4 platforms', '30 posts + 8 reels per month', 'Paid campaign management', 'Influencer outreach', 'Dedicated account manager' ), 'Weekly review', 'Starts in 3 days', 'Priority support' ),
			),
		),
		array(
			'slug' => 'google-ads', 'title' => 'Google Ads Management', 'icon' => 'ads', 'billing' => 'monthly',
			'tagline' => 'High-intent traffic and measurable leads from Search, Display and YouTube.',
			'body' => 'We build and manage Google Ads campaigns that focus on profitable leads: keyword research, ad copy, landing-page recommendations, conversion tracking and continuous optimisation. Ad spend is paid directly to Google.',
			'highlights' => array( 'Search, Display & YouTube campaigns', 'Conversion tracking setup', 'Weekly optimisation', 'Transparent reporting' ),
			'process' => array( 'Plan | Goals, budget and keyword strategy.', 'Launch | Campaign build and tracking.', 'Optimise | Bids, ads and audiences.', 'Report | ROI-focused reporting.' ),
			'addons' => array( array( 'label' => 'Landing page design', 'price' => 6000, 'recurring' => 0 ) ),
			'tiers' => array(
				'basic' => $tier( array( 5999 ), array( '1 campaign (Search)', 'Keyword research', 'Conversion tracking', 'Monthly report' ), 'Monthly review', 'Live in 3–5 days', 'Email support' ),
				'professional' => $tier( array( 11999 ), array( 'Up to 3 campaigns', 'Search + Display', 'Remarketing audiences', 'A/B ad testing' ), 'Fortnightly review', 'Live in 3–5 days', 'Email & call support' ),
				'enterprise' => $tier( array( 24999 ), array( 'Unlimited campaigns', 'Search, Display, YouTube & Shopping', 'Advanced attribution', 'Dedicated ads manager' ), 'Weekly review', 'Live in 3–5 days', 'Priority support' ),
			),
		),
		array(
			'slug' => 'online-promotions', 'title' => 'Online Promotions', 'icon' => 'promo', 'billing' => 'one_time',
			'tagline' => 'Launch campaigns, offers and events with coordinated multi-channel promotion.',
			'body' => 'Planning a launch, festival offer or event? We run coordinated campaigns across email, WhatsApp, social media and display so your message reaches the right audience at the right time.',
			'highlights' => array( 'Multi-channel campaign plan', 'Creative & copy included', 'Email & WhatsApp broadcast', 'Campaign performance report' ),
			'process' => $proc,
			'addons' => array( array( 'label' => 'Additional channel', 'price' => 2500, 'recurring' => 0 ) ),
			'tiers' => array(
				'basic' => $tier( array( 3999 ), array( '1 campaign', '2 channels', 'Creative & copy', 'Summary report' ), '2 revisions', '5 days', '7 days' ),
				'professional' => $tier( array( 8999 ), array( '3 channels', 'Email list segmentation', 'Landing page', 'Detailed analytics' ), '3 revisions', '7–10 days', '15 days' ),
				'enterprise' => $tier( array( 17999 ), array( 'All channels', 'Influencer collaborations', 'Retargeting ads', 'Dedicated campaign manager' ), 'Unlimited revisions', '2 weeks', '30 days', '+' ),
			),
		),
		array(
			'slug' => 'website-maintenance', 'title' => 'Website Maintenance & Support', 'icon' => 'support', 'billing' => 'monthly',
			'tagline' => 'Keep your website secure, fast and up to date — without lifting a finger.',
			'body' => 'Outdated plugins and missed backups are the top causes of hacked and broken sites. Our maintenance plans cover updates, backups, security scans, uptime monitoring and small content changes.',
			'highlights' => array( 'Core, theme & plugin updates', 'Daily backups', 'Security & uptime monitoring', 'Content change requests' ),
			'process' => array( 'Audit | Health check of your site.', 'Protect | Backups, firewall and updates.', 'Monitor | 24x7 uptime checks.', 'Improve | Monthly fixes and tweaks.' ),
			'addons' => array( array( 'label' => 'Extra 2 hours of development', 'price' => 2000, 'recurring' => 1 ) ),
			'tiers' => array(
				'basic' => $tier( array( 1499 ), array( 'Monthly updates', 'Weekly backups', 'Uptime monitoring', '1 hour of changes' ), 'n/a', 'Starts in 2 days', 'Email support' ),
				'professional' => $tier( array( 2999 ), array( 'Fortnightly updates', 'Daily backups', 'Malware scan & cleanup', '3 hours of changes', 'Performance report' ), 'n/a', 'Starts in 2 days', 'Priority email support' ),
				'enterprise' => $tier( array( 5999 ), array( 'Weekly updates', 'Daily off-site backups', '8 hours of changes', 'Speed optimisation', 'Phone & WhatsApp support' ), 'n/a', 'Starts in 2 days', '24x7 critical support' ),
			),
		),
		array(
			'slug' => 'content-marketing', 'title' => 'Content Marketing', 'icon' => 'content', 'billing' => 'monthly',
			'tagline' => 'Search-optimised articles, case studies and copy that attract and convert.',
			'body' => 'We research topics your customers search for and produce in-depth, expert-reviewed content that builds authority, earns links and nurtures leads through the buying journey.',
			'highlights' => array( 'Keyword-led content strategy', 'Expert-written articles', 'Editorial calendar', 'On-page SEO included' ),
			'process' => array( 'Research | Audience and keyword research.', 'Plan | Editorial calendar.', 'Write | Drafts, edits and design.', 'Publish | Upload, optimise and promote.' ),
			'addons' => array( array( 'label' => 'Case study (1,000 words)', 'price' => 3500, 'recurring' => 0 ) ),
			'tiers' => array(
				'basic' => $tier( array( 3999 ), array( '4 articles per month (800 words)', 'Keyword research', 'On-page SEO' ), '1 revision per article', 'Weekly delivery', 'Email support' ),
				'professional' => $tier( array( 7999 ), array( '8 articles per month (1,200 words)', 'Featured images', 'Internal linking', 'Editorial calendar' ), '2 revisions per article', 'Twice-weekly delivery', 'Email & call support' ),
				'enterprise' => $tier( array( 14999 ), array( '16 articles per month', 'Whitepapers & case studies', 'Email newsletter copy', 'Dedicated content strategist' ), 'Unlimited revisions', 'Daily delivery', 'Priority support' ),
			),
		),
	),
	'faqs' => array(
		array( 'How long does it take to build a website?', 'A typical business website takes 1–3 weeks from the day we receive your content. E-commerce stores and custom applications take longer depending on scope; the delivery timeline is shown on every package.' ),
		array( 'Which payment methods do you accept?', 'We accept UPI, credit and debit cards, net banking and wallets through our secure payment gateway, as well as bank transfer (NEFT/IMPS). All payments are processed on the gateway\'s secure servers — we never see or store your card details.' ),
		array( 'Is GST included in the prices?', 'Package prices are shown excluding GST. Applicable GST is calculated at checkout and shown on your tax invoice. If you have a GSTIN, enter it at checkout to receive a GST invoice in your company name.' ),
		array( 'Can I customise a package?', 'Yes. Pick optional add-ons on the service page, or send us a quotation request for a fully custom scope and we will reply with a tailored quote.' ),
		array( 'Do I own my website and files after delivery?', 'Yes. On full payment, the final deliverables and source files are transferred to you. Third-party licences (premium plugins, stock assets) are noted in your proposal.' ),
		array( 'What happens after I place an order?', 'You receive an order confirmation and invoice by email, then submit your requirements from your dashboard. We assign a team member, share milestones and keep you updated until delivery.' ),
		array( 'How do revisions work?', 'Each package lists the number of revision rounds included. A revision round is a consolidated set of changes sent together. Extra revisions can be purchased if needed.' ),
		array( 'Do you offer refunds?', 'Please see our Refund Policy for details. In general, work that has not started can be cancelled for a refund minus gateway charges; work in progress is billed for completed milestones.' ),
		array( 'Will my website be mobile friendly and SEO ready?', 'Every website we build is responsive and includes technical SEO foundations — clean URLs, meta tags, sitemap, schema markup and speed optimisation.' ),
		array( 'Do you provide support after launch?', 'Yes. Every package includes a support period, and you can extend it with a monthly Website Maintenance plan.' ),
	),
);
