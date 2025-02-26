        // Array of random texts
        const texts = [
            "Sample text",
            "the most cool imageboard, fr",
            "вставить текст",
            "😎",
            "did you touched the grass today?",
            "open scripts folder to find out where tf this coming from",
        ];

        // Function to generate random text
        function getRandomText() {
            const randomIndex = Math.floor(Math.random() * texts.length);
            return texts[randomIndex];
        }

        // Display random text after page load
        window.onload = function() {
            const randomTextDiv = document.getElementById('randomText');
            randomTextDiv.textContent = getRandomText();
        };