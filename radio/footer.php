<?php
	$footer_array = array(
			array(
					'title' => 'Main Page',
					'link'  => './'
				),
			array(
					'title' => 'Profile',
					'link'  => 'profile.php'
				),
			array(
					'title' => 'Band Plan',
					'link'  => 'band_plan.php'
				),
			array(
					'title' => 'QRZ',
					'link'  => 'qrz.php'
				),
			array(
					'title' => 'Map Contacts',
					'link'  => 'map_contacts.php'
				),
			array(
					'title' => 'SOTA Chasing',
					'link'  => 'sota.php'
				),
			array(
					'title' => 'Import',
					'link'  => 'scrape.php'
				)
		);

	print '<div class="menu-container hidemeprint">
				<!-- Hamburger Icon -->
				<button class="hamburger-btn" onclick="toggleMenu()" aria-label="Toggle menu">
					<span></span>
					<span></span>
					<span></span>
				</button>

				<!-- Navigation Links -->
				<div class="menu-items" id="dropdown-menu">';
					foreach ($footer_array as $value) print '<a href="'.$value['link'].'">'.$value['title'].'</a>';
				print '</div>
			</div>
<div class="solar-container">
  <!-- Reuses your existing .hamburger-btn CSS for the icon styling -->
  <button class="hamburger-btn" id="solar-toggle-btn" aria-label="Toggle Solar Data">
    <span></span>
    <span></span>
    <span></span>
  </button>

  <div class="solar-content" id="solar-popup">
    <img id="solar-data-img" src="https://www.hamqsl.com/solarn0nbh.php" alt="Solar Data and Propagation">
  </div>
</div>';
?>

<script>
  // Toggles the visibility of the dropdown menu and the button animation
  function toggleMenu() {
    document.getElementById("dropdown-menu").classList.toggle("show");
    
    // Select the top hamburger button and toggle the 'open' class for the 'X' animation
    document.querySelector('.menu-container .hamburger-btn').classList.toggle("open");
  }

  // Close the dropdowns if the user clicks outside of them
  window.onclick = function(event) {
    // Check if the click was outside ANY hamburger button
    if (!event.target.matches('.hamburger-btn') && !event.target.closest('.hamburger-btn')) {
      
      // 1. Handle the top dropdown menu
      var dropdowns = document.getElementsByClassName("menu-items");
      for (var i = 0; i < dropdowns.length; i++) {
        var openDropdown = dropdowns[i];
        if (openDropdown.classList.contains('show')) {
          openDropdown.classList.remove('show');
          // Reset the top button back to hamburger lines
          document.querySelector('.menu-container .hamburger-btn').classList.remove("open");
        }
      }

      // 2. Handle the bottom solar popup
      var solarPopup = document.getElementById("solar-popup");
      var solarBtn = document.getElementById("solar-toggle-btn");
      
      if (solarPopup && solarPopup.classList.contains("show")) {
        // If clicking outside while the solar popup is open, close it
        // (Optional: add && !event.target.closest('.solar-content') to the main IF statement 
        // if you want to allow people to click the actual image without it closing)
        solarPopup.classList.remove("show");
        // Reset the bottom button back to hamburger lines
        if (solarBtn) solarBtn.classList.remove("open");
      }
    }
  }
  
  document.addEventListener("DOMContentLoaded", () => {
    const solarBtn = document.getElementById("solar-toggle-btn");
    const solarPopup = document.getElementById("solar-popup");
    const solarImg = document.getElementById("solar-data-img");
    
    const baseUrl = "https://www.hamqsl.com/solarn0nbh.php";
    const refreshInterval = 5 * 60 * 1000; // 5 minutes

    // Toggle visibility and animate button independently of the top menu
    solarBtn.addEventListener("click", () => {
      solarPopup.classList.toggle("show");
      solarBtn.classList.toggle("open"); // Using 'open' here so both menus share the same CSS animation
    });

    // Refresh the solar image every 5 minutes by appending a timestamp
    setInterval(() => {
      const timestamp = new Date().getTime();
      solarImg.src = `${baseUrl}?t=${timestamp}`;
    }, refreshInterval);
  });
</script>