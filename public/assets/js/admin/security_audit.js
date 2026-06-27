/**
 * Zero CMS - Back-Office Security Audit Page Handler
 * 100% Zero-Dependency Interactive Async Audit & Markdown Parser
 */
document.addEventListener('DOMContentLoaded', function () {
    const btnTrigger = document.getElementById('btn-trigger-audit');
    const triggerBox = document.getElementById('audit-trigger-box');
    const loader = document.getElementById('audit-progress-loader');
    const viewer = document.getElementById('audit-report-viewer');

    if (!btnTrigger) return;

    btnTrigger.addEventListener('click', function () {
        // Toggle active visual states
        triggerBox.style.display = 'none';
        loader.style.display = 'flex';
        viewer.style.display = 'none';

        // Async AJAX handshake to SecurityAuditController
        fetch('/admin/security/audit?ajax=1')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not compliant.');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.report) {
                    // Translate raw Markdown report body into semantic HTML in-browser
                    const parsedHtml = parseMarkdownToHtml(data.report);
                    viewer.innerHTML = parsedHtml;

                    // Transition layout elements
                    loader.style.display = 'none';
                    viewer.style.display = 'block';
                } else {
                    showError('Failed to parse secure audit payload configuration.');
                }
            })
            .catch(error => {
                showError('Security audit handshake failed: ' + error.message);
            });
    });

    // Interactive Scorecard click listeners
    const cardScore = document.getElementById('card-score');
    const cardAdmins = document.getElementById('card-admins');
    const cardSites = document.getElementById('card-sites');
    const cardAlerts = document.getElementById('card-alerts');
    const quickAttentionBox = document.getElementById('quick-attention-box');

    if (cardScore && quickAttentionBox) {
        cardScore.addEventListener('click', function() {
            if (quickAttentionBox.style.display === 'none') {
                quickAttentionBox.style.display = 'block';
                quickAttentionBox.scrollIntoView({ behavior: 'smooth' });
            } else {
                quickAttentionBox.style.display = 'none';
            }
        });
    }

    if (cardAdmins) {
        cardAdmins.addEventListener('click', function() {
            window.location.href = '/admin/list/users';
        });
    }

    if (cardSites) {
        cardSites.addEventListener('click', function() {
            window.location.href = '/admin/list/sites';
        });
    }

    if (cardAlerts) {
        cardAlerts.addEventListener('click', function() {
            window.location.href = '/admin/list/audit_logs';
        });
    }

    // Bind click events on "View Report" history buttons for instant browser-side Markdown rendering
    const btnViewReports = document.querySelectorAll('.btn-view-report');
    btnViewReports.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = btn.getAttribute('data-id');
            const mdTextarea = document.getElementById('markdown-archive-' + id);
            if (mdTextarea && viewer) {
                const parsedHtml = parseMarkdownToHtml(mdTextarea.value);
                viewer.innerHTML = parsedHtml;
                viewer.style.display = 'block';
                viewer.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    /**
     * Pure, zero-dependency, regex-driven Markdown to Semantic HTML translator
     */
    function parseMarkdownToHtml(md) {
        let html = md;

        // Clean out raw carriage returns
        html = html.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

        // Translate triple-backticks code blocks (```lang code ```)
        html = html.replace(/```(?:[a-zA-Z0-9_\-\+]+)?\n([\s\S]*?)```/g, function (match, codeText) {
            const escapedCode = escapeHtml(codeText.trim());
            return `<pre><code>${escapedCode}</code></pre>`;
        });

        // Translate headings (H1, H2, H3)
        html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
        html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
        html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');

        // Translate bold text (**text**)
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Translate inline monospaced code (`code`)
        html = html.replace(/`(.*?)`/g, '<code>$1</code>');

        // Translate bullet point lines (* text or - text)
        html = html.replace(/^\* (.*$)/gim, '<li>$1</li>');
        html = html.replace(/^- (.*$)/gim, '<li>$1</li>');

        // Assemble separate lines into valid paragraphs and wrap raw text cleanly
        const lines = html.split('\n');
        let insideList = false;
        const processedLines = [];

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();

            if (line.startsWith('<li>')) {
                if (!insideList) {
                    processedLines.push('<ul>');
                    insideList = true;
                }
                processedLines.push(line);
            } else {
                if (insideList) {
                    processedLines.push('</ul>');
                    insideList = false;
                }

                // Wrap normal non-markup text paragraphs safely
                if (line && 
                    !line.startsWith('<h1>') && 
                    !line.startsWith('<h2>') && 
                    !line.startsWith('<h3>') && 
                    !line.startsWith('<pre>') && 
                    !line.startsWith('</pre>') && 
                    !line.startsWith('<code>') && 
                    !line.startsWith('</code>') &&
                    !line.startsWith('<ul>') &&
                    !line.startsWith('</ul>') &&
                    !line.startsWith('---')) {
                    processedLines.push('<p>' + line + '</p>');
                } else if (line === '---') {
                    processedLines.push('<hr style="border: none; border-bottom: 1px solid #1e293b; margin: 2rem 0;">');
                } else {
                    processedLines.push(lines[i]); // Keep pre, h1, h2, h3 intact
                }
            }
        }

        if (insideList) {
            processedLines.push('</ul>');
        }

        return processedLines.join('\n');
    }

    /**
     * Escape raw HTML entities inside parsed code blocks for safety
     */
    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Render a styled high-contrast fallback error notification card
     */
    function showError(message) {
        loader.style.display = 'none';
        viewer.innerHTML = `
            <div style="background-color: #7f1d1d; border: 1px solid #b91c1c; border-radius: var(--border-radius); padding: 1.5rem; color: #fecaca; font-weight: 600;">
                <h3 style="margin-top: 0; color: #ffffff;">[ERROR] Security Audit Failed</h3>
                <p style="margin-bottom: 0;">${message}</p>
            </div>
        `;
        viewer.style.display = 'block';
    }
});
