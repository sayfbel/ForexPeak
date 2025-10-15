const sideMenu = document.querySelector('aside');
const menuBtn = document.getElementById('menu-btncheckbox');
const closeBtn = document.getElementById('close-btn');
const percentageValue = document.getElementById('percentageValue');
const progressCircle = document.getElementById('progressCircle');
const darkMode = document.querySelector('.dark-mode');
const userProfile = document.querySelector('.active-user-profile');
const main = document.querySelector('main');
const languageSelect = document.getElementById('language-select');
const settingsButton = document.getElementById('settings-button');
const themeSelect = document.getElementById('theme-select');
let initialAmount = 1000;
let currentAmount = 1000;
let totalTrades = 0;
let wins = 0;
let losses = 0;
let profitLossData = [initialAmount];
const winrateElement = document.getElementById("winrate");
const pnlElement = document.getElementById("pnl");
const totalTradesElement = document.getElementById("totalTrades");
const winLossElement = document.getElementById("winLoss");
const closeAddAccountDiv = document.getElementById('closeAddAccountDiv');
const AccountButoninfo = document.getElementById('AccountButoninfo');
const AddAccountDiv = document.getElementById('AddAccountDiv');

function applyTheme(isDarkMode) {
    if (isDarkMode) {
        document.body.classList.add('dark-mode-variables');
        localStorage.setItem('theme', 'dark');

        document.getElementById('blacktradingview').style.display = 'none';
        document.getElementById('lighttradingview').style.display = 'block';

        
        document.getElementById('newsdark').style.display = 'none';
        document.getElementById('newslight').style.display = 'block';

        darkMode.querySelector('span:nth-child(1)').classList.remove('active');
        darkMode.querySelector('span:nth-child(2)').classList.add('active');
        
    } else {
        document.body.classList.remove('dark-mode-variables');
        localStorage.setItem('theme', 'light');

        document.getElementById('blacktradingview').style.display = 'block';
        document.getElementById('lighttradingview').style.display = 'none';

        document.getElementById('newsdark').style.display = 'block';
        document.getElementById('newslight').style.display = 'none';

        darkMode.querySelector('span:nth-child(1)').classList.add('active');
        darkMode.querySelector('span:nth-child(2)').classList.remove('active');
    }
}

function handleThemeChange(isDarkMode) {
    applyTheme(isDarkMode);

    themeSelect.value = isDarkMode ? 'dark' : 'light';
}
function switchLanguage(language) {
    const translatableElements = document.querySelectorAll('[data-arabe], [data-france], [data-english]');
    
    translatableElements.forEach(element => {
        if (language === 'Arabe') {
            element.textContent = element.getAttribute('data-arabe');
        } else if (language === 'French') {
            element.textContent = element.getAttribute('data-france');
        } else {
            element.textContent = element.getAttribute('data-english');
        }
    });
}

function setLanguage() {
    const savedLanguage = localStorage.getItem('selectedLanguage') || 'English'; // Default to English
    languageSelect.value = savedLanguage;
    switchLanguage(savedLanguage);
}

settingsButton.addEventListener('click', function() {
    const selectedLanguage = languageSelect.value;
    localStorage.setItem('selectedLanguage', selectedLanguage);
    switchLanguage(selectedLanguage);

    // Get the selected theme from the dropdown
    const selectedTheme = themeSelect.value;
    handleThemeChange(selectedTheme === 'dark'); // Update theme based on selection
    
    location.reload();
});

document.addEventListener('DOMContentLoaded', () => {
    // Set initial language
    setLanguage();

    // Set initial theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    const isDarkMode = savedTheme === 'dark';
    handleThemeChange(isDarkMode); // Initialize theme based on saved value

    // Set the theme select box to the saved theme
    themeSelect.value = savedTheme; // Ensure the select shows the current theme
});

darkMode.addEventListener('click', () => {
    const isActive = document.body.classList.contains('dark-mode-variables');
    handleThemeChange(!isActive); // Toggle the theme
});

document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme') || 'light';
    themeSelect.value = savedTheme; // Set the select box to the saved theme
    themeSelect.dispatchEvent(new Event('change')); // Trigger change event to set the theme
});

menuBtn.addEventListener('change', () => {
    if (menuBtn.checked) {
        sideMenu.style.display = 'block';
        userProfile.style.transform = 'translateX(0%)';
    } else {
        sideMenu.style.display = 'none';
        userProfile.style.transform = 'translateX(150%)';
    }
});
function switchTabuser(tabIndex) {
    // Get all tab content containers
    const tabContents = [
        document.querySelector('.welcome-container'),
        document.querySelector('.support-container'),
        document.querySelector('.premium-container'),
        document.querySelector('.System-container'),
        document.querySelector('.About-container')
    ];

    // Get all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button-user');

    // Hide all tab content and remove active class from all buttons
    tabContents.forEach((content, index) => {
        if (content) {
            content.style.display = 'none'; // Hide the content
        }
        tabButtons[index].classList.remove('active'); // Remove active class
    });

    // Show the selected tab content and add active class to the clicked button
    if (tabContents[tabIndex]) {
        tabContents[tabIndex].style.display = 'flex'; // Show selected content
    }
    if (tabButtons[tabIndex]) {
        tabButtons[tabIndex].classList.add('active'); // Add active class to the clicked button
    }
}
document.addEventListener('DOMContentLoaded', () => {
    switchTabuser(0); // Display the first tab on load
});
closeBtn.addEventListener('click', () => {
    sideMenu.style.display = 'none';
});
function switchTab(index) {
    const tabs = document.querySelectorAll('.tab-content');
    const buttons = document.querySelectorAll('.tab-button');

    tabs.forEach((tab, i) => {
        tab.style.display = i === index ? 'block' : 'none';
        buttons[i].classList.toggle('active', i === index);
    });
}
Orders.forEach(order => {
    const tr = document.createElement('tr');
    const trContent = `
        <td>${order.productName}</td>
        <td>${order.productNumber}</td>
        <td>${order.paymentStatus}</td>
        <td class="${order.status === 'Declined' ? 'danger' : order.status === 'Pending' ? 'warning' : 'primary'}">${order.status}</td>
        <td class="primary">Details</td>
    `;
    tr.innerHTML = trContent;
    document.querySelector('table tbody').appendChild(tr);
});
const navLinks = document.querySelectorAll('.nav-link');
const mainSections = {
    'AnalyticsLink': document.getElementById('AnalyticsMain'),
    'UsersLink': document.getElementById('UsersMain'),
    'HistoryLink': document.getElementById('HistoryMain'),
    'CommunityLink': document.getElementById('CommunityMain'),
    'TicketsLink': document.getElementById('TicketsMain'),
    'BacktestingLink': document.getElementById('BacktestMain'),
    'InventoryLink': document.getElementById('InventoryMain'),
    'ReportsLink': document.getElementById('ReportsMain'),
    'SettingsLink': document.getElementById('SettingsMain'),
    'ReplayLink': document.getElementById('SettingsMain'),
    'OthersLink' : document.getElementById('OthersMain'),

};
function handleSectionClick() {
    for (const section in mainSections) {
        if (mainSections.hasOwnProperty(section)) {
            const mainElement = mainSections[section];
            const portfolioMain = document.getElementById("PortfolioTraders");
            mainElement.addEventListener('click', () => {
                menuBtn.checked = false; // Uncheck the menu button
                sideMenu.style.display = 'none'; // Hide the side menu
                userProfile.style.transform = 'translateX(150%)'; // Move the user profile off screen
            });
        }
    }
}
function checkScreenWidth() {
    if (window.innerWidth < 1200) {
        handleSectionClick();
    }
}
checkScreenWidth();
window.addEventListener('resize', checkScreenWidth);
function showSection(linkId) {
    // Check if the screen width is greater than 1200px
     // Remove the 'active' class from all nav-link elements
     navLinks.forEach(item => item.classList.remove('active'));

     // Add the 'active' class to the clicked nav-link element
     const activeLink = document.getElementById(linkId);
     if (activeLink) {
         activeLink.classList.add('active');
     }

     // Hide all main sections
     Object.values(mainSections).forEach(section => {
         section.style.display = 'none';
     });

     // Show the main section corresponding to the clicked link
     if (mainSections[linkId]) {
         mainSections[linkId].style.display = 'block';
         const portfolioMain = document.getElementById("PortfolioTraders");
         portfolioMain.style.display = 'none';
     }
    if (window.innerWidth > 1200) {

        // Check if ReportsLink, InventoryLink, or TicketsLink is active
        if (linkId === 'InventoryLink') {
            // Hide rightSection
            document.getElementById('rightSection').style.display = 'none';

            // Modify grid-template-columns for ReportsMain
            document.querySelector('.container').style.gridTemplateColumns = '11rem auto 0';
        } else {
            // Show right section for other links (if needed)
            document.getElementById('rightSection').style.display = 'block';

            // Reset grid-template-columns for .container
            document.querySelector('.container').style.gridTemplateColumns = ''; // Reset to default or other style
        }
    } else {
        // If the screen width is less than or equal to 1200px, don't activate the function
        console.log("Screen width is less than 1200px. Function not activated.");
    }
}
function showMoreRows() {
    showSection('HistoryLink');
    window.location.href = '#trackrecordall';
}    
navLinks.forEach(link => {
    link.addEventListener('click', function() {
        // Save the ID of the clicked link to localStorage
        localStorage.setItem('activeLinkId', this.id);

        // Show the section corresponding to the clicked link
        showSection(this.id);
    });
});


window.addEventListener('load', () => {
    const activeLinkId = localStorage.getItem('activeLinkId');
    if (activeLinkId) {
        showSection(activeLinkId);
    } else {
        // Default to showing the first link or a specific default section
        showSection('AnalyticsLink');
    }
});
AccountButoninfo.addEventListener('click', () => {
    AddAccountDiv.style.display = 'block';
});
closeAddAccountDiv.addEventListener('click', () => {
    AddAccountDiv.style.display = 'none';
});
function calculateLotSize() {
    var accountBalance = parseFloat(document.getElementById("accountBalance").value);
    var riskPercentage = parseFloat(document.getElementById("riskPercentage").value);
    var stopLoss = parseFloat(document.getElementById("stopLoss").value);

    // Calculate lot size
    var lotSize = ((accountBalance * (riskPercentage / 100)) / stopLoss) / 10;

    if (lotSize === 0 || isNaN(lotSize)) {
        document.getElementById("resultlot").innerHTML = "<p>You can't</p>";
    } else {
        document.getElementById("resultlot").innerHTML = "<p>Lot Size: " + lotSize.toFixed(2) + " standard lots</p>";
    }
}
function calculateCompounding() {
    const principal = parseFloat(document.getElementById('principal').value);
    const rate = parseFloat(document.getElementById('rate').value);
    const time = parseInt(document.getElementById('time').value);
    
    if (isNaN(principal) || isNaN(rate) || isNaN(time) || principal <= 0 || rate <= 0 || time <= 0) {
        document.getElementById('resulte').innerText = "Please enter valid inputs.";
        return;
    }

    const compounded = principal * Math.pow(1 + rate / 12, time);
    document.getElementById('resulte').innerText = "Result: $" + compounded.toFixed(2);
}
function calculatePips() {
    const enterPips = parseFloat(document.getElementById('EntrePips').value);
    const targetPips = parseFloat(document.getElementById('TargetPips').value);
    const stopLossPips = parseFloat(document.getElementById('stopLossPips').value);
    const isBuy = document.getElementById('buySellSwitch').checked;
    let targetResult, stoplossResult;
    let pipValue = 1; // Default pip value

    // Detect the pair type based on the entered price
    if (enterPips >= 1000) {
        pipValue = 10; // XAUUSD, XAGUSD, etc.
    } else if (enterPips >= 100) {
        pipValue = 1; // GBPJPY, EURJPY, etc.
    } else {
        pipValue = 10000; // EURUSD, GBPUSD, etc.
    }

    if (isBuy) {
        if (targetPips >= enterPips || enterPips >= stopLossPips) {
            alert('For Sell: Target price must be lower than Enter price and Enter price must be lower than Stop Loss price.');
            return;
        }
        targetResult = (enterPips - targetPips) * pipValue;
        stoplossResult = (stopLossPips - enterPips) * pipValue;
        
    } else {
       if (targetPips <= enterPips || enterPips <= stopLossPips) {
            alert('For Buy: Target price must be higher than Enter price and Enter price must be higher than Stop Loss price.');
            return;
        }
        targetResult = (targetPips - enterPips) * pipValue;
        stoplossResult = (enterPips - stopLossPips) * pipValue;
    }

    document.getElementById('Targetresult').innerText = `Target Pips: ${targetResult.toFixed(2)}`;
    document.getElementById('Stoplossresult').innerText = `Stop Loss Pips: ${stoplossResult.toFixed(2)}`;
}

function calculateMartingale() {
    // Get input values
    const principal = parseFloat(document.getElementById("martingaleprincipal").value);
    const rate = parseFloat(document.getElementById("martingalerate").value) / 100; // Convert percentage to decimal
    const losses = parseInt(document.getElementById("martingalelosses").value);

    // Validate input
    if (isNaN(principal) || isNaN(rate) || isNaN(losses) || principal <= 0 || rate <= 0 || losses < 0) {
        alert("Please enter valid values.");
        return;
    }

    // Martingale calculation
    let totalAmount = 0;
    let currentAmount = principal * rate;
    
    for (let i = 0; i <= losses; i++) {
        totalAmount += currentAmount;
        currentAmount *= 2; // Double the bet for each loss
    }

    // Update result
    document.getElementById("resultmartingale").innerHTML = 
        "Total Required Amount: $" + totalAmount.toFixed(2);
}
function updateProgress() {
 let percentage = parseInt(percentageValue.textContent.replace('%', ''));

 // Ensure the percentage is within the 0-100 range
 percentage = Math.max(0, Math.min(percentage, 100));
 // Update the circle stroke offset (226.08 is the full perimeter of the circle)
 const offset = 226.08 - (226.08 * percentage) / 100;
 progressCircle.style.strokeDashoffset = offset;
}
const ctx = document.getElementById('chart-backtest').getContext('2d');
let chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: Array.from({ length: profitLossData.length }, (_, i) => i + 1),
        datasets: [{
            label: 'Balance',
            data: profitLossData,
            borderColor: 'rgb(75, 192, 192)',
            fill: false,
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: false
            }
        }
    }
});
document.getElementById("setInitial").addEventListener("click", () => {
    initialAmount = parseFloat(document.getElementById("initial").value);
    currentAmount = initialAmount;
    profitLossData = [initialAmount];
    updateStats(); // Update the stats on the UI
    updateChart(); // Re-render the chart
});
document.getElementById("addProfit").addEventListener("click", () => {
    let profit = parseFloat(document.getElementById("profit").value);
    if (!isNaN(profit)) {
        currentAmount += profit;
        totalTrades++;
        wins++;
        profitLossData.push(currentAmount);
        updateStats(); // Update stats whenever the user adds profit
        updateChart(); // Re-render the chart with new data
    }
});
document.getElementById("addLoss").addEventListener("click", () => {
    let loss = parseFloat(document.getElementById("loss").value);
    if (!isNaN(loss)) {
        currentAmount -= loss;
        totalTrades++;
        losses++;
        profitLossData.push(currentAmount);
        updateStats(); // Update stats whenever the user adds a loss
        updateChart(); // Re-render the chart with new data
    }
});
function updateStats() {
    // Calculate winrate
    let winrate = totalTrades > 0 ? (wins / totalTrades) * 100 : 0;
    
    // Calculate PnL percentage
    let pnl = ((currentAmount - initialAmount) / initialAmount) * 100;

    // Update the DOM with new values
    winrateElement.textContent = winrate.toFixed(2) + "%";
    pnlElement.textContent = pnl.toFixed(2) + "%";
    totalTradesElement.textContent = totalTrades;
    winLossElement.textContent = `${wins}W / ${losses}L`;
}
function updateChart() {
    chart.data.labels = Array.from({ length: profitLossData.length }, (_, i) => i + 1);
    chart.data.datasets[0].data = profitLossData;
    chart.update();
}
