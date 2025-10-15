function scrollToElement(elementSelector, instance = 0) {
    // Select all elements that match the given selector
    const elements = document.querySelectorAll(elementSelector);
    // Check if there are elements matching the selector and if the requested instance exists
    if (elements.length > instance) {
        // Scroll to the specified instance of the element
        elements[instance].scrollIntoView({ behavior: 'smooth' });
    }
}

const link1 = document.getElementById("link1");
const link2 = document.getElementById("link2");
const link3 = document.getElementById("link3");

link1.addEventListener('click', () => {
    scrollToElement('.header');
});

link2.addEventListener('click', () => {
    // Scroll to the second element with "header" class
    scrollToElement('.header', 1);
});

link3.addEventListener('click', () => {
    scrollToElement('.column');
});
function calculateAverageRiskReward(data) {
    let totalRR = 0; // Total Risk to Reward ratio
    let count = 0; // Count of valid trades

    data.forEach(item => {
        const entry = parseFloat(item.entry) || 0;
        const tp = parseFloat(item.tp) || 0;
        const sl = parseFloat(item.sl) || 0;

        if (entry && tp && sl) {
            const reward = Math.abs(tp - entry);
            const risk = Math.abs(entry - sl);

            // Calculate R:R only if risk is not zero
            if (risk > 0) {
                const rr = reward / risk;
                totalRR += rr;
                count++;
            }
        }
    });

    // Calculate average R:R
    const averageRR = count > 0 ? (totalRR / count).toFixed(2) : 0;

    // Display average R:R in the <h4> element
    document.getElementById("riskreward").textContent = `Average Risk to Reward ratio: ${averageRR}`;
    
    return averageRR;
}

// Call the function with your data
const averageRiskReward = calculateAverageRiskReward(data);
