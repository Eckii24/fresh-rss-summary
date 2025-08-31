// Initialize when DOM is ready
if (document.readyState && document.readyState !== 'loading') {
    configureSummaryButtons();
} else {
    document.addEventListener('DOMContentLoaded', configureSummaryButtons, false);
}

function configureSummaryButtons() {
    // Use event delegation to handle summary button clicks
    document.getElementById('global').addEventListener('click', function (e) {
        for (var target = e.target; target && target != this; target = target.parentNode) {
            
            // Make sure button text is visible when article is displayed
            if (target.matches('.flux_header')) {
                const summaryBtn = target.nextElementSibling?.querySelector('.gemini-summary-btn');
                if (summaryBtn && !summaryBtn.textContent.trim()) {
                    summaryBtn.textContent = 'Summary';
                }
            }

            // Handle summary button clicks
            if (target.matches('.gemini-summary-btn')) {
                e.preventDefault();
                e.stopPropagation();
                if (target.dataset.request) {
                    handleSummaryButtonClick(target);
                }
                break;
            }
        }
    }, false);
}

function handleSummaryButtonClick(button) {
    const container = button.parentNode;
    const contentDiv = container.querySelector('.gemini-summary-content');
    
    // If summary is already visible, toggle it
    if (contentDiv.classList.contains('visible')) {
        contentDiv.classList.remove('visible');
        button.textContent = 'Summary';
        return;
    }
    
    // If we already have content, just show it
    if (contentDiv.innerHTML && !container.classList.contains('error')) {
        contentDiv.classList.add('visible');
        button.textContent = 'Hide Summary';
        return;
    }
    
    // Otherwise, fetch the summary
    fetchSummary(container, button);
}

async function fetchSummary(container, button) {
    const contentDiv = container.querySelector('.gemini-summary-content');
    
    // Set loading state
    container.classList.add('loading');
    container.classList.remove('error', 'youtube');
    button.disabled = true;
    button.textContent = 'Loading...';
    contentDiv.innerHTML = 'Generating summary...';
    contentDiv.classList.add('visible');
    
    try {
        const url = button.dataset.request;
        const formData = new FormData();
        formData.append('ajax', 'true');
        formData.append('_csrf', context.csrf);
        
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Unknown error occurred');
        }
        
        // Set success state
        container.classList.remove('loading');
        if (data.is_youtube) {
            container.classList.add('youtube');
        }
        
        // Display the summary
        contentDiv.innerHTML = formatSummaryText(data.summary);
        button.textContent = 'Hide Summary';
        button.disabled = false;
        
    } catch (error) {
        console.error('Summary fetch error:', error);
        
        // Set error state
        container.classList.remove('loading');
        container.classList.add('error');
        contentDiv.innerHTML = `Error: ${error.message}`;
        button.textContent = 'Retry Summary';
        button.disabled = false;
    }
}

function formatSummaryText(text) {
    // Basic markdown-like formatting
    let formatted = text
        // Convert line breaks to <br>
        .replace(/\n\n/g, '</p><p>')
        .replace(/\n/g, '<br>')
        // Basic bold formatting
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        // Basic italic formatting
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        // Basic bullet points
        .replace(/^\- (.*?)(<br>|$)/gm, '<li>$1</li>')
        .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
    
    // Wrap in paragraphs if not already formatted
    if (!formatted.includes('<p>') && !formatted.includes('<ul>')) {
        formatted = '<p>' + formatted + '</p>';
    } else if (formatted.includes('<p>')) {
        formatted = '<p>' + formatted + '</p>';
    }
    
    return formatted;
}