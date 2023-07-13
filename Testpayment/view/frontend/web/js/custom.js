console.log("script loaded");

var script = document.createElement('script');
script.src = 'https://websdk-assets.s3.ap-south-1.amazonaws.com/shopify-messaging-app/sample.js';
document.head.appendChild(script);
