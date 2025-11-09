// Import the functions you need from the SDKs you need
import { initializeApp } from "firebase/app";
import { getAnalytics } from "firebase/analytics";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
  apiKey: "AIzaSyB-vwKT_nnbFT1UQykpw7e6VqLSeUVBkTc",
  authDomain: "ewan-geniuses.firebaseapp.com",
  projectId: "ewan-geniuses",
  storageBucket: "ewan-geniuses.firebasestorage.app",
  messagingSenderId: "73208499391",
  appId: "1:73208499391:web:b17fffb8c982ab34644a0a",
  measurementId: "G-EP2J15LZZQ"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const analytics = getAnalytics(app);