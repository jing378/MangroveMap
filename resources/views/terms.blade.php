@php
    // Shown as the contact address in section 15. Set MAIL_FROM_ADDRESS to the project's real inbox.
    $contactEmail = config('mail.from.address');
    $lastUpdated = 'November 4, 2026';
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Terms and Conditions - MangroveMap</title>
    <link rel="icon" type="image/png" href="/icon-192.png" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', system-ui, -apple-system, Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
            color: #1a2e1a;
            line-height: 1.65;
            background: radial-gradient(900px 500px at 10% 0%, rgba(16, 185, 129, 0.12), transparent 55%),
                radial-gradient(900px 500px at 90% 10%, rgba(59, 130, 246, 0.08), transparent 55%),
                #f5f7f6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .terms-header {
            background: linear-gradient(135deg, #0b3a2b 0%, #0f3a45 100%);
            color: #fff;
            padding: 40px 20px 36px;
        }

        .terms-header-inner,
        .terms-main {
            max-width: 860px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: rgba(226, 232, 240, 0.9);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .back-link:hover {
            color: #fff;
        }

        .terms-header h1 {
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .terms-header p {
            margin-top: 8px;
            font-size: 14px;
            color: rgba(226, 232, 240, 0.9);
        }

        .terms-main {
            padding: 28px 20px 56px;
        }

        .terms-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.45);
            border-radius: 18px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
            padding: 32px 36px;
        }

        .terms-intro {
            font-size: 15px;
            color: #365a45;
            padding-bottom: 20px;
            margin-bottom: 8px;
            border-bottom: 1px solid #e2e8e4;
        }

        .toc {
            margin: 18px 0 8px;
            padding: 16px 20px;
            background: #edf7f2;
            border: 1px solid #b0e0c0;
            border-radius: 12px;
        }

        .toc strong {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #365a45;
            margin-bottom: 8px;
        }

        .toc ol {
            columns: 2;
            column-gap: 28px;
            padding-left: 20px;
            font-size: 13.5px;
        }

        .toc a {
            color: #1e7a4f;
            text-decoration: none;
        }

        .toc a:hover {
            text-decoration: underline;
        }

        section {
            padding-top: 24px;
            scroll-margin-top: 16px;
        }

        h2 {
            font-size: 18px;
            font-weight: 800;
            color: #1a2e1a;
            margin-bottom: 8px;
        }

        p,
        li {
            font-size: 14.5px;
            color: #2f4a3a;
        }

        p+p,
        p+ul,
        ul+p {
            margin-top: 10px;
        }

        section ul {
            padding-left: 22px;
        }

        section li+li {
            margin-top: 5px;
        }

        .note {
            margin-top: 12px;
            padding: 12px 14px;
            border-left: 4px solid #1e9e62;
            background: #f3faf6;
            border-radius: 0 10px 10px 0;
        }

        a {
            color: #1e7a4f;
        }

        .terms-footer {
            text-align: center;
            margin-top: 22px;
            font-size: 13px;
            color: #7a8a7f;
        }

        @media (max-width: 640px) {
            .terms-header h1 {
                font-size: 24px;
            }

            .terms-card {
                padding: 22px 18px;
            }

            .toc ol {
                columns: 1;
            }
        }

        @media print {
            body {
                background: #fff;
            }

            .terms-header {
                background: none;
                color: #000;
                padding-bottom: 0;
            }

            .terms-header p {
                color: #333;
            }

            .back-link,
            .toc {
                display: none;
            }

            .terms-card {
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
    @include('partials.scrollbar-styles')
</head>

<body>
    <header class="terms-header">
        <div class="terms-header-inner">
            <a class="back-link" href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M12.5 16.25L5.75 9.5L12.5 2.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Back
            </a>
            <h1>Terms and Conditions</h1>
            <p>Web-Based Mangrove Extent Mapping, Genus Classification, and Expert-Based Planting Suitability Recommendation System &middot; Last updated {{ $lastUpdated }}</p>
        </div>
    </header>

    <main class="terms-main">
        <article class="terms-card">
            <p class="terms-intro">
                These Terms and Conditions ("Terms") govern your use of MangroveMap (the "System"), a web platform for
                mapping mangrove areas in Southern Leyte, Philippines, classifying mangrove genera, and sharing
                expert-based planting suitability recommendations. By creating an account or using the System, you
                agree to these Terms. If you do not agree, please do not use the System.
            </p>

            <nav class="toc" aria-label="Contents">
                <strong>Contents</strong>
                <ol>
                    <li><a href="#about">About the System</a></li>
                    <li><a href="#accounts">Accounts and Eligibility</a></li>
                    <li><a href="#roles">User Roles</a></li>
                    <li><a href="#contributions">Delineations and Contributions</a></li>
                    <li><a href="#imagery">Drone Imagery Uploads</a></li>
                    <li><a href="#ai">AI Segmentation and Classification</a></li>
                    <li><a href="#recommendations">Planting Recommendations</a></li>
                    <li><a href="#acceptable-use">Acceptable Use</a></li>
                    <li><a href="#privacy">Privacy and Personal Data</a></li>
                    <li><a href="#moderation">Review, Moderation, and Removal</a></li>
                    <li><a href="#ip">Intellectual Property</a></li>
                    <li><a href="#disclaimer">Disclaimers</a></li>
                    <li><a href="#liability">Limitation of Liability</a></li>
                    <li><a href="#changes">Changes and Governing Law</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ol>
            </nav>

            <section id="about">
                <h2>1. About the System</h2>
                <p>
                    The System lets registered users draw mangrove delineation polygons on a map, upload drone
                    orthomosaic imagery for automated mangrove segmentation, and view mangrove extent, genus, and
                    planting suitability information. Experts review contributions and give recommendations, and
                    administrators manage accounts, map layers, and the AI models used by the System.
                </p>
                <p>
                    The System supports mangrove research, conservation, and community participation. It is not an
                    official government registry, and nothing in it replaces the maps, surveys, or decisions of the
                    Department of Environment and Natural Resources (DENR), local government units, or other
                    authorities.
                </p>
            </section>

            <section id="accounts">
                <h2>2. Accounts and Eligibility</h2>
                <ul>
                    <li>You must give accurate and complete information when you register, including your real name
                        and a working email address, and keep it up to date.</li>
                    <li>You must verify your email address before you can use the System's mapping features.</li>
                    <li>If you are under 18, you may use the System only with the consent and supervision of a parent,
                        guardian, or teacher.</li>
                    <li>You are responsible for keeping your password confidential and for all activity under your
                        account. Tell an administrator right away if you think your account has been used without your
                        permission.</li>
                    <li>Each person may hold only one account. Accounts may not be shared, sold, or transferred.</li>
                </ul>
            </section>

            <section id="roles">
                <h2>3. User Roles</h2>
                <p>The System has three roles:</p>
                <ul>
                    <li><strong>Residents</strong> can view the map, submit delineations for expert review, revise
                        rejected submissions, and upload drone imagery.</li>
                    <li><strong>Experts</strong> are mangrove specialists who approve or reject delineations, record
                        yearly observations, and give planting recommendations.</li>
                    <li><strong>Administrators</strong> manage users, expert applications, map layers, activity logs,
                        and AI models.</li>
                </ul>
                <p>
                    You may apply for an expert account when you register. Your organization and qualifications are
                    reviewed by an administrator, who may approve or reject the application at their discretion. Until
                    it is approved, your account remains a resident account. Experts must give honest, good-faith
                    reviews and recommendations within their area of expertise, and must disclose any conflict of
                    interest that could affect a review.
                </p>
            </section>

            <section id="contributions">
                <h2>4. Delineations and Contributions</h2>
                <ul>
                    <li>Delineations you submit should reflect your honest, best-effort observation of mangrove areas.
                        Do not submit polygons you know to be false or misleading.</li>
                    <li>Submissions are reviewed by experts and may be approved, rejected, or edited. A rejection is
                        not a penalty, and you may revise and resubmit a rejected delineation.</li>
                    <li>The System keeps the revision history of delineations and observations so that changes can be
                        traced.</li>
                    <li><strong>Approved delineations are public.</strong> They appear on the System's public map,
                        together with the name of the contributor, and may be visible to anyone who visits the
                        System.</li>
                    <li>The System automatically labels your polygons with a barangay and municipality based on their
                        location. These labels are approximate.</li>
                </ul>
            </section>

            <section id="imagery">
                <h2>5. Drone Imagery Uploads</h2>
                <ul>
                    <li>Upload only imagery that you captured yourself or that you have permission to use and share.
                    </li>
                    <li>You are responsible for following the laws that apply to your drone flights, including the
                        rules of the Civil Aviation Authority of the Philippines (CAAP) and any permits needed for
                        protected areas.</li>
                    <li>Do not upload imagery that shows people, homes, or private property in a way that invades
                        privacy, or imagery unrelated to mangrove mapping.</li>
                    <li>Uploaded files are stored privately. Processed tiles and segmentation results are available
                        only to you and to administrators, unless you confirm them as a delineation, which then goes
                        through the normal review process.</li>
                    <li>Processing large files can take a long time and may fail. The System may limit file size and
                        the number of uploads, and does not guarantee that every file can be processed.</li>
                </ul>
            </section>

            <section id="ai">
                <h2>6. AI Segmentation and Classification</h2>
                <p>
                    The System uses machine-learning models to detect mangrove areas in imagery and to classify
                    mangrove genera. These results are generated automatically and can be wrong. They are offered as
                    drafts and aids, not as verified facts.
                </p>
                <p>
                    You should check AI-generated boundaries before you confirm them. AI output becomes part of the
                    shared map only after it has been confirmed and reviewed.
                </p>
            </section>

            <section id="recommendations">
                <h2>7. Planting Recommendations</h2>
                <p>
                    Planting suitability recommendations reflect the opinion of the reviewing expert based on the
                    information available to them. They are advisory only.
                </p>
                <div class="note">
                    A recommendation in the System is <strong>not</strong> a permit or authorization to plant, cut,
                    clear, or develop any area. Mangrove areas are generally protected under Philippine law. Before
                    any planting or rehabilitation activity, coordinate with the DENR (CENRO/PENRO), the local
                    government unit, and the affected community, and secure any required permits.
                </div>
            </section>

            <section id="acceptable-use">
                <h2>8. Acceptable Use</h2>
                <p>You agree not to:</p>
                <ul>
                    <li>submit false, misleading, offensive, or unlawful content;</li>
                    <li>impersonate another person or misrepresent your qualifications or affiliation;</li>
                    <li>use the System to plan or justify illegal cutting, conversion, reclamation, or other harm to
                        mangrove areas;</li>
                    <li>try to access accounts, files, or pages that you are not authorized to see;</li>
                    <li>upload malware or files designed to disrupt the System;</li>
                    <li>overload the System with automated requests, scraping, or repeated large uploads;</li>
                    <li>copy, resell, or republish data from the System in a way that breaks these Terms or the
                        rights of other contributors.</li>
                </ul>
            </section>

            <section id="privacy">
                <h2>9. Privacy and Personal Data</h2>
                <p>
                    The System processes personal data in line with the Data Privacy Act of 2012 (Republic Act No.
                    10173). We collect:
                </p>
                <ul>
                    <li><strong>Account information:</strong> your name, email address, optional contact number, and
                        password (stored only in encrypted, hashed form).</li>
                    <li><strong>Expert application information:</strong> your organization and qualifications, if you
                        apply.</li>
                    <li><strong>Contributions:</strong> the delineations, observations, recommendations, and imagery
                        you submit, with their dates and locations.</li>
                    <li><strong>Activity logs:</strong> the actions you take in the System, together with your IP
                        address and browser information, kept for security and auditing.</li>
                </ul>
                <p>
                    We use this data to run the System, verify accounts, review contributions, notify you about your
                    submissions, keep the System secure, and support mangrove research and conservation. We do not
                    sell your personal data. Approved delineations show your name publicly, as described in Section 4.
                    Contributions and imagery may be used to evaluate and improve the System's mapping and AI models.
                </p>
                <p>
                    You may ask to access, correct, or delete your personal data by contacting us (see Section 15).
                    Some records, such as approved delineations and activity logs, may be kept after an account is
                    deleted where they are needed for the integrity of the map or for security.
                </p>
            </section>

            <section id="moderation">
                <h2>10. Review, Moderation, and Removal</h2>
                <p>
                    Experts and administrators may approve, reject, edit, hide, or delete delineations, imagery, and
                    other content to keep the map accurate and safe. Administrators may change a user's role, or
                    suspend or delete an account, if the user breaks these Terms or misuses the System. Where
                    practical, we will tell you why.
                </p>
                <p>You may stop using the System at any time and may ask an administrator to delete your account.</p>
            </section>

            <section id="ip">
                <h2>11. Intellectual Property</h2>
                <p>
                    You keep ownership of the content and imagery you submit. By submitting it, you give the System
                    and its operators a non-exclusive, royalty-free license to store, process, display, and share it
                    within the System and for mangrove research, education, and conservation purposes, including
                    showing approved delineations on the public map.
                </p>
                <p>
                    The System's software, design, and compiled map data belong to its developers and operators.
                    Basemaps and other third-party data shown in the System remain the property of their providers
                    and are subject to their own terms.
                </p>
            </section>

            <section id="disclaimer">
                <h2>12. Disclaimers</h2>
                <p>
                    The System is provided "as is" and "as available". Maps, boundaries, area figures, AI results, and
                    recommendations may be incomplete, out of date, or inaccurate. Do not use them as the sole basis
                    for legal, land-ownership, boundary, navigation, or safety decisions. We do not guarantee that
                    the System will always be available, free of errors, or free of data loss, so keep your own
                    copies of important files.
                </p>
            </section>

            <section id="liability">
                <h2>13. Limitation of Liability</h2>
                <p>
                    To the extent allowed by law, the System's developers, operators, and reviewing experts are not
                    liable for any loss or damage arising from your use of, or reliance on, the System or its
                    content, including decisions made based on maps, AI output, or recommendations.
                </p>
            </section>

            <section id="changes">
                <h2>14. Changes and Governing Law</h2>
                <p>
                    We may update these Terms from time to time. The "Last updated" date at the top shows when they
                    last changed. If you continue to use the System after a change, you accept the updated Terms.
                </p>
                <p>These Terms are governed by the laws of the Republic of the Philippines.</p>
            </section>

            <section id="contact">
                <h2>15. Contact</h2>
                <p>
                    For questions about these Terms, data privacy requests, or to report misuse, contact the System
                    administrators
                    @if ($contactEmail)
                        at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
                    @else
                        through the System.
                    @endif
                </p>
            </section>
        </article>

        <p class="terms-footer">&copy; {{ date('Y') }} MangroveMap. Empowering coastal communities through spatial science.</p>
    </main>
</body>

</html>
