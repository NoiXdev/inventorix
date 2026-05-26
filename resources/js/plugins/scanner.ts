import QrScanner from "qr-scanner";

const scannerVideoElement = document.querySelector("video#scanner-area") as HTMLVideoElement | null;
const scannerStartButton = document.querySelector("#scanner-start-button");
const scannerOutputInput = document.querySelector("#scanner-code") as HTMLInputElement | null;

if(!scannerVideoElement) {
    throw new Error("Video Element not found")
}

let scanner = new QrScanner(scannerVideoElement, result => {
    if(scannerOutputInput){
        scannerOutputInput.value = result;
    }
    console.log(result);
});

scannerStartButton?.addEventListener('click', () => {
    scanner.start();
})

document.addEventListener("scanner-modal-close", () => {
    scanner.stop();
})
