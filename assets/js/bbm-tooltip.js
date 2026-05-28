/**
 * Bible by Midvash - Tooltip Handler
 * 
 * Handles tooltip display for Bible reference links
 * Fetches verse content via AJAX and displays in a styled tooltip
 * 
 * @package Bible_by_Midvash
 * @version 1.0.0
 */

(function() {
    'use strict';

    // Configuration from WordPress (passed via wp_localize_script)
    const config = window.bbm_config || {
        ajax_url: '/wp-admin/admin-ajax.php',
        nonce: '',
        version: 'nvt',
        show_version: true,
        fallback_message: 'Verse currently unavailable',
        read_more: 'Read more',
        locale: 'pt-br',
        site_url: 'https://midvash.com',
        icon_url: ''
    };

    // Cache for fetched verses
    const verseCache = new Map();

    // Current tooltip element
    let tooltipElement = null;
    let activeLink = null;
    let hideTimeout = null;
    let showTimeout = null;

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Create tooltip element
     */
    function createTooltip() {
        if (tooltipElement) return tooltipElement;

        tooltipElement = document.createElement('div');
        tooltipElement.className = 'bbm-tooltip bbm-tooltip--below';
        tooltipElement.setAttribute('role', 'tooltip');
        tooltipElement.setAttribute('aria-hidden', 'true');

        // Static SVG markup is fine via innerHTML (no untrusted input), but
        // the icon URL comes from PHP config — build it as an actual <img>
        // node so aggressive theme sanitizers (e.g. AMP-mode plugins) can't
        // strip the attribute.
        tooltipElement.innerHTML = `
            <div class="bbm-tooltip__header">
                <span class="bbm-tooltip__reference"></span>
                <a class="bbm-tooltip__version-link" target="_blank" rel="noopener noreferrer">
                    <span class="bbm-tooltip__version"></span>
                </a>
            </div>
            <div class="bbm-tooltip__content">
                <p class="bbm-tooltip__text"></p>
            </div>
            <div class="bbm-tooltip__footer">
                <a class="bbm-tooltip__read-more" target="_blank" rel="noopener noreferrer">
                    <svg class="bbm-tooltip__read-more-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    <span class="bbm-tooltip__read-more-text"></span>
                </a>
            </div>
        `;

        if (config.icon_url) {
            const header = tooltipElement.querySelector('.bbm-tooltip__header');
            const img    = document.createElement('img');
            img.src       = config.icon_url;
            img.alt       = 'Midvash';
            img.className = 'bbm-tooltip__logo';
            header.insertBefore(img, header.firstChild);
        }

        document.body.appendChild(tooltipElement);

        // Add event listeners for tooltip hover (keep visible when hovering tooltip)
        tooltipElement.addEventListener('mouseenter', function() {
            clearTimeout(hideTimeout);
        });

        tooltipElement.addEventListener('mouseleave', function() {
            hideTooltip();
        });

        return tooltipElement;
    }

    /**
     * Position tooltip near the link
     */
    function positionTooltip(link) {
        if (!tooltipElement || !link) return;

        const linkRect = link.getBoundingClientRect();
        const tooltipRect = tooltipElement.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        // Calculate position
        let top = linkRect.bottom + scrollTop + 10;
        let left = linkRect.left + scrollLeft;

        // Check if tooltip would go off the right edge
        if (left + tooltipRect.width > window.innerWidth - 20) {
            left = window.innerWidth - tooltipRect.width - 20;
        }

        // Check if tooltip would go off the left edge
        if (left < 20) {
            left = 20;
        }

        // Check if tooltip would go below the viewport
        const spaceBelow = window.innerHeight - linkRect.bottom;
        const spaceAbove = linkRect.top;

        if (spaceBelow < tooltipRect.height + 20 && spaceAbove > tooltipRect.height + 20) {
            // Position above the link
            top = linkRect.top + scrollTop - tooltipRect.height - 10;
            tooltipElement.classList.remove('bbm-tooltip--below');
            tooltipElement.classList.add('bbm-tooltip--above');
        } else {
            tooltipElement.classList.remove('bbm-tooltip--above');
            tooltipElement.classList.add('bbm-tooltip--below');
        }

        tooltipElement.style.top = top + 'px';
        tooltipElement.style.left = left + 'px';
    }

    /**
     * Show loading state in tooltip
     */
    function showLoading(reference) {
        const tooltip = createTooltip();
        
        tooltip.classList.add('bbm-tooltip--loading');
        tooltip.classList.remove('bbm-tooltip--error');
        
        tooltip.querySelector('.bbm-tooltip__reference').textContent = reference;
        const versionLink = tooltip.querySelector('.bbm-tooltip__version-link');
        const versionSpan = tooltip.querySelector('.bbm-tooltip__version');
        if (config.show_version) {
            versionSpan.textContent = config.version.toUpperCase();
            const versionUrl = config.site_url + '/' + config.locale + '/' + config.version.toLowerCase();
            versionLink.href = versionUrl;
            versionLink.style.display = '';
        } else {
            versionLink.style.display = 'none';
        }
        tooltip.querySelector('.bbm-tooltip__content').innerHTML = '<div class="bbm-tooltip__loader"></div>';
        tooltip.querySelector('.bbm-tooltip__footer').style.display = 'none';
    }

    /**
     * Show verse content in tooltip
     */
    function showContent(data, url) {
        const tooltip = createTooltip();
        
        tooltip.classList.remove('bbm-tooltip--loading', 'bbm-tooltip--error');
        
        // Set reference
        const reference = data.reference || data.book + ' ' + data.chapter + ':' + data.verse;
        tooltip.querySelector('.bbm-tooltip__reference').textContent = reference;
        
        // Set version with link
        const versionLink = tooltip.querySelector('.bbm-tooltip__version-link');
        const versionSpan = tooltip.querySelector('.bbm-tooltip__version');
        if (config.show_version && data.version) {
            const version = data.version.toUpperCase();
            versionSpan.textContent = version;
            
            // Build version URL: site_url/locale/version
            const versionUrl = config.site_url + '/' + config.locale + '/' + config.version.toLowerCase();
            versionLink.href = versionUrl;
            versionLink.style.display = '';
            versionSpan.style.display = '';
        } else {
            versionLink.style.display = 'none';
        }
        
        // Set content
        let contentHtml = '';
        if (data.verses && Array.isArray(data.verses)) {
            // Multiple verses
            contentHtml = data.verses.map((verse, index) => {
                const verseNum = data.verse + index;
                return '<sup class="bbm-tooltip__verse-number">' + verseNum + '</sup>' + escapeHtml(verse);
            }).join(' ');
        } else if (data.text) {
            // Single verse
            contentHtml = escapeHtml(data.text);
        }
        
        tooltip.querySelector('.bbm-tooltip__content').innerHTML = 
            '<p class="bbm-tooltip__text">' + contentHtml + '</p>';
        
        // Set read more link
        const readMoreLink = tooltip.querySelector('.bbm-tooltip__read-more');
        const readMoreText = tooltip.querySelector('.bbm-tooltip__read-more-text');
        readMoreLink.href = url;
        readMoreText.textContent = config.read_more || 'Read more';
        readMoreLink.style.display = '';
        tooltip.querySelector('.bbm-tooltip__footer').style.display = '';
        
        // Update position after content change
        positionTooltip(activeLink);
    }

    /**
     * Show error state in tooltip
     */
    function showError(message) {
        const tooltip = createTooltip();
        
        tooltip.classList.remove('bbm-tooltip--loading');
        tooltip.classList.add('bbm-tooltip--error');
        
        tooltip.querySelector('.bbm-tooltip__content').innerHTML = `
            <svg class="bbm-tooltip__error-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            <p class="bbm-tooltip__error-message">${escapeHtml(message || config.fallback_message)}</p>
        `;
        
        tooltip.querySelector('.bbm-tooltip__footer').style.display = 'none';
    }

    /**
     * Show tooltip
     */
    function showTooltip(link) {
        clearTimeout(hideTimeout);
        clearTimeout(showTimeout);

        activeLink = link;
        
        const reference = link.getAttribute('data-midvash-ref') || link.textContent;
        const url = link.href;
        const cacheKey = reference + '_' + config.version;

        // Create and position tooltip
        createTooltip();
        
        // Check cache first
        if (verseCache.has(cacheKey)) {
            const cached = verseCache.get(cacheKey);
            showContent(cached, url);
        } else {
            showLoading(reference);
            fetchVerse(reference, url, cacheKey);
        }

        // Position and show
        positionTooltip(link);
        
        showTimeout = setTimeout(function() {
            tooltipElement.classList.add('bbm-tooltip--visible');
            tooltipElement.setAttribute('aria-hidden', 'false');
        }, 100);
    }

    /**
     * Hide tooltip
     */
    function hideTooltip() {
        clearTimeout(showTimeout);
        
        hideTimeout = setTimeout(function() {
            if (tooltipElement) {
                tooltipElement.classList.remove('bbm-tooltip--visible');
                tooltipElement.setAttribute('aria-hidden', 'true');
            }
            activeLink = null;
        }, 150);
    }

    /**
     * Fetch verse from server
     */
    function fetchVerse(reference, url, cacheKey) {
        const params = new URLSearchParams({
            action: 'bbm_get_verse',
            nonce: config.nonce,
            reference: reference,
            version: config.version
        });

        fetch(config.ajax_url + '?' + params.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(result) {
            if (result.success && result.data) {
                // Cache the result
                verseCache.set(cacheKey, result.data);
                
                // Only update if this is still the active tooltip
                if (activeLink && activeLink.getAttribute('data-midvash-ref') === reference) {
                    showContent(result.data, url);
                }
            } else {
                throw new Error(result.data?.message || 'Verse not found');
            }
        })
        .catch(function(error) {
            if (window.bbm_config && window.bbm_config.debug && typeof console !== 'undefined') {
                console.error('Midvash Tooltip Error:', error);
            }
            if (activeLink && activeLink.getAttribute('data-midvash-ref') === reference) {
                showError(config.fallback_message);
            }
        });
    }

    /**
     * Initialize event listeners
     */
    function init() {
        // Use event delegation for efficiency
        document.addEventListener('mouseenter', function(e) {
            const link = e.target.closest('.bbm-link');
            if (link) {
                showTooltip(link);
            }
        }, true);

        document.addEventListener('mouseleave', function(e) {
            const link = e.target.closest('.bbm-link');
            if (link) {
                hideTooltip();
            }
        }, true);

        // Handle focus for accessibility
        document.addEventListener('focusin', function(e) {
            const link = e.target.closest('.bbm-link');
            if (link) {
                showTooltip(link);
            }
        });

        document.addEventListener('focusout', function(e) {
            const link = e.target.closest('.bbm-link');
            if (link) {
                hideTooltip();
            }
        });

        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && tooltipElement && tooltipElement.classList.contains('bbm-tooltip--visible')) {
                hideTooltip();
            }
        });

        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (activeLink && tooltipElement && tooltipElement.classList.contains('bbm-tooltip--visible')) {
                    positionTooltip(activeLink);
                }
            }, 100);
        });

        // Handle scroll
        let scrollTimeout;
        window.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                if (activeLink && tooltipElement && tooltipElement.classList.contains('bbm-tooltip--visible')) {
                    positionTooltip(activeLink);
                }
            }, 50);
        }, { passive: true });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
