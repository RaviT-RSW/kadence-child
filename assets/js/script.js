jQuery(document).ready(function ($)
{

    // Handle Appointment cancel button
    $('.cancel-btn').on('click', function () {
        const itemId = $(this).data('item-id');
        const orderId = $(this).data('order-id');

        if (confirm('Are you sure you want to cancel this appointment?')) {
            $.ajax({
                url: php.ajax_url,
                type: 'POST',
                data: {
                    action: 'cancel_session',
                    item_id: itemId,
                    order_id: orderId,
                    nonce: php.mentor_dashboard_nonce
                },
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        showNotification('Failed to cancel: ' + response.message, 'danger');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showNotification('An error occurred. Please try again.', 'danger');
                }
            });
        }
    });


    // Handle Appointment Approve button
    $('.approve-appoinment-btn').on('click', function () {
        const itemId = $(this).data('item-id');
        const orderId = $(this).data('order-id');

        if (confirm('Are you sure you want to approve this appointment?')) {
            $.ajax({
                url: php.ajax_url,
                type: 'POST',
                data: {
                    action: 'approve_session',
                    item_id: itemId,
                    order_id: orderId,
                    nonce: php.mentor_dashboard_nonce
                },
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        showNotification('Failed to approve: ' + response.message, 'danger');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showNotification('An error occurred. Please try again.', 'danger');
                }
            });
        }
    });

});

// Change the channel name in wise chat pro chatbox start
jQuery(document).ready(function($) {
    // Dynamic list of channel names (will be populated automatically)
    let channelNames = new Set();
    const processedNodes = new WeakSet();

    const transformChannelName = (channelName) => {
        const spaceIndex = channelName.indexOf(' ');
        return spaceIndex !== -1 ? channelName.substring(spaceIndex + 1).trim() : channelName;
    };

    const replaceChannelNames = () => {
        if (channelNames.size === 0) return;

        // Create a single, efficient regex for all channel names
        const namesToReplace = Array.from(channelNames)
            .map(escapeRegExp)
            .join('|');
        const regex = new RegExp(`(${namesToReplace})`, 'g');

        const allTextNodes = $('*').contents().filter(function() {
            return this.nodeType === 3; // Text nodes only
        });

        allTextNodes.each(function() {
            if (processedNodes.has(this)) return;

            let nodeValue = this.nodeValue;
            if (regex.test(nodeValue)) {
                this.nodeValue = nodeValue.replace(regex, (match) => {
                    const transformedName = transformChannelName(match);
                    return transformedName;
                });
                processedNodes.add(this);
            }
        });
    };

    const escapeRegExp = (string) => string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    const detectChannelNames = () => {
        const detectedNames = new Set();
        const textNodePatterns = [
            /\b\w+_\d+_\w+_\d+\s+\w+-\w+\b/g,
            /\b\w+_\d+_\w+_\d+\s+\w+_\w+\b/g,
        ];
        const elementPatterns = [
            /\b\w+_\d+_\w+_\d+\b/g,
            /\b\w+_\w+_\d+\b/g,
            /\b\w+_\d+_\w+\b/g,
            /\b\w+\s+\w+_\d+\b/g,
        ];

        // Method 1: Text node scanning
        $('*').contents().filter(function() {
            return this.nodeType === 3;
        }).each(function() {
            const text = this.nodeValue.trim();
            if (text.length > 0) {
                textNodePatterns.forEach(pattern => {
                    const matches = text.match(pattern);
                    if (matches) matches.forEach(match => detectedNames.add(match.trim()));
                });
            }
        });

        // Method 2: Element-specific scanning
        const selectors = [
            'div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'td', 'th', 'label', 'a',
            '[class*="channel"]', '[class*="name"]', '[id*="channel"]', '[id*="name"]'
        ];
        $(selectors.join(', ')).each(function() {
            const text = $(this).text().trim();
            if (text.length > 0) {
                elementPatterns.forEach(pattern => {
                    const matches = text.match(pattern);
                    if (matches) matches.forEach(match => detectedNames.add(match.trim()));
                });
            }
        });

        return detectedNames;
    };

    const refreshChannelNames = () => {
        const newNames = detectChannelNames();
        const currentNames = new Set(channelNames);
        const addedNames = new Set([...newNames].filter(x => !currentNames.has(x)));

        if (addedNames.size > 0) {
            addedNames.forEach(name => {
                console.log(`Newly detected channel name: "${name}"`);
            });
            channelNames = newNames;
            replaceChannelNames();
        }

        Array.from(channelNames).forEach((name, index) => {
            console.log(`${index + 1}. "${name}"`);
        });

        return channelNames;
    };

    const addChannelName = (name) => {
        if (!channelNames.has(name)) {
            channelNames.add(name);
            replaceChannelNames();
        }
    };

    // Initial setup
    channelNames = detectChannelNames();
    replaceChannelNames();

    // Attach public functions to the window for debugging
    window.refreshChannelNames = refreshChannelNames;
    window.getChannelNames = () => Array.from(channelNames);
    window.addChannelName = addChannelName;

    // Create an observer for dynamic content changes
    const observer = new MutationObserver(function(mutations) {
        if (mutations.some(m => m.addedNodes.length > 0)) {
            refreshChannelNames();
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true // Also observe changes within text nodes
    });

    setTimeout(function() {
        // Hide global tab
        $('.wcTabs .wcTab .wcName').each(function() {
            if ($(this).text().trim() === 'global') {
                $(this).closest('.wcTab').hide();
            }
        });

        // Hide global channel
        $('.wcPublicChannels .wcName').each(function() {
            if ($(this).text().trim() === 'global') {
                $(this).closest('.wcChannelTrigger').hide();
            }
        });
    }, 1000);

});

// Change the channel name in wise chat pro chatbox end