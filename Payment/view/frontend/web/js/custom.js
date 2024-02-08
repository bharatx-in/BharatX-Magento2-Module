const hostname = window.location.hostname;
console.log("script loaded", hostname);


var script = document.createElement('script');
script.src = 'https://websdk-assets.s3.ap-south-1.amazonaws.com/magento-scripts/' + hostname + '.js';
document.head.appendChild(script);
