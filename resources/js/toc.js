// Table of Contents Generator
document.addEventListener("DOMContentLoaded", function () {
    const contentArea = document.querySelector("article.prose");
    const tocNav = document.getElementById("table-of-contents");

    if (!contentArea || !tocNav) return;

    const headings = Array.from(contentArea.querySelectorAll("h1, h2, h3, h4"));

    if (headings.length === 0) return;

    // Track section positions and corresponding links
    const sectionPositions = [];
    const tocLinks = [];

    // Create TOC
    headings.forEach((heading) => {
        // Skip h1 (usually the title)
        if (heading.tagName.toLowerCase() === "h1") return;

        // Add ID to heading if it doesn't have one
        if (!heading.id) {
            heading.id = heading.textContent.toLowerCase().replace(/\s+/g, "-");
        }

        // Create link
        const link = document.createElement("a");
        link.textContent = heading.textContent;
        link.href = `#${heading.id}`;
        link.className =
            "block py-1 px-2 rounded transition-colors duration-200 text-slate-700 hover:text-indigo-600 hover:bg-slate-100";
        link.dataset.target = heading.id;

        // Indent based on heading level
        if (heading.tagName.toLowerCase() === "h3") {
            link.classList.add("ml-3");
        } else if (heading.tagName.toLowerCase() === "h4") {
            link.classList.add("ml-6");
        }

        // Add to TOC
        tocNav.appendChild(link);

        // Store the section position and corresponding link
        sectionPositions.push(heading.offsetTop);
        tocLinks.push(link);
    });

    // Add active class to current section's TOC link
    function highlightActiveSection() {
        const scrollPosition = window.scrollY + 100; // Offset for better UX

        // Find the current section
        let currentSection = 0;
        for (let i = 0; i < sectionPositions.length; i++) {
            if (scrollPosition >= sectionPositions[i]) {
                currentSection = i;
            }
        }

        // Remove active class from all links
        tocLinks.forEach((link) => {
            link.classList.remove(
                "bg-slate-100",
                "text-indigo-600",
                "font-medium"
            );
        });

        // Add active class to current section's link
        if (tocLinks[currentSection]) {
            tocLinks[currentSection].classList.add(
                "bg-slate-100",
                "text-indigo-600",
                "font-medium"
            );

            // Ensure the active link is visible in the sidebar
            const activeLink = tocLinks[currentSection];
            const sidebarContainer = document.querySelector("aside > div");
            if (sidebarContainer) {
                const linkTop = activeLink.offsetTop;
                const containerScrollTop = sidebarContainer.scrollTop;
                const containerHeight = sidebarContainer.offsetHeight;

                if (
                    linkTop < containerScrollTop ||
                    linkTop > containerScrollTop + containerHeight
                ) {
                    sidebarContainer.scrollTop = linkTop - containerHeight / 2;
                }
            }
        }
    }

    // Handle scroll events
    window.addEventListener("scroll", highlightActiveSection);

    // Initialize active section
    setTimeout(highlightActiveSection, 100);

    // Handle clicks on TOC links for smooth scrolling
    tocLinks.forEach((link) => {
        link.addEventListener("click", function (e) {
            e.preventDefault();
            const targetId = this.getAttribute("href").substring(1);
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 20,
                    behavior: "smooth",
                });

                // Update URL hash without jumping
                history.pushState(null, null, `#${targetId}`);
            }
        });
    });
});
