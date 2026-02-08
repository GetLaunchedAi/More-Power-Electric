//
//    Toggle Mobile Navigation
//
const navbarMenu = document.querySelector("#navigation #navbar-menu");
const hamburgerMenu = document.querySelector("#navigation .hamburger-menu");
const serviceMenu = document.querySelector("#navigation .dropdown");
const about = document.querySelector('#About\\ Us')
const contact = document.querySelector('#Contact')
const projects = document.querySelector('#Projects')

const screenWidth = window.screen.width;



hamburgerMenu.addEventListener('click', function () {
    const isNavOpen = navbarMenu.classList.contains("open");
    if (!isNavOpen) {
        hamburgerMenu.setAttribute("aria-expanded", true);
        hamburgerMenu.classList.add("clicked");
        navbarMenu.classList.add("open");
    } else {
        hamburgerMenu.setAttribute("aria-expanded", false);
        hamburgerMenu.classList.remove("clicked");
        navbarMenu.classList.remove("open");
    }
});

serviceMenu.addEventListener('click', function () {
    if (window.innerWidth >= 1024) return; // Don't handle click on desktop
    
    const isServiceOpen = serviceMenu.classList.contains("open");
    if (!isServiceOpen) {
        serviceMenu.setAttribute("aria-expanded", true);
        serviceMenu.classList.add("open");
        
        // Only adjust other menu items if we're on mobile
        if (window.innerWidth < 1024) {
            if (about) about.style.display = 'none';
            if (contact) contact.style.display = 'none';
            if (projects) projects.style.display = 'none';
        }
    } else {
        serviceMenu.setAttribute("aria-expanded", false);
        serviceMenu.classList.remove("open");
        
        if (window.innerWidth < 1024) {
            if (about) about.style.display = 'block';
            if (contact) contact.style.display = 'block';
            if (projects) projects.style.display = 'block';
        }
    }
});