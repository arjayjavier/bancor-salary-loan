<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
$isLoggedIn = false;
$user = null;

if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
    $pdo = getDBConnection();
    if ($pdo !== null) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.role, u.status
            FROM sessions s
            INNER JOIN users u ON s.user_id = u.id
            WHERE s.session_token = ?
              AND s.is_active = TRUE
              AND s.expires_at > CURRENT_TIMESTAMP
              AND u.status = 'active'
        ");
        $stmt->execute([$_SESSION['session_token']]);
        $user = $stmt->fetch();
        $isLoggedIn = $user !== false;
    }
}

// Redirect to login if not authenticated
if (!$isLoggedIn) {
    header('Location: ../index.php');
    exit;
}

// Check if user is BAD payer and block access
if ($isLoggedIn && $user) {
    $pdo = getDBConnection();
    if ($pdo !== null) {
        // Get user's credit score override
        $creditScoreOverride = null;
        try {
            $stmt = $pdo->prepare("SELECT credit_score_override FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();
            $creditScoreOverride = $userData && isset($userData['credit_score_override']) ? $userData['credit_score_override'] : null;
        } catch (PDOException $e) {
            $creditScoreOverride = null;
        }
        
        // Check credit status
        $stmt = $pdo->prepare("
            SELECT status FROM (
                SELECT status FROM e_loans WHERE user_id = ?
                UNION ALL
                SELECT status FROM atm_loans WHERE user_id = ?
            ) as all_loans
        ");
        $stmt->execute([$user['id'], $user['id']]);
        $allLoans = $stmt->fetchAll();
        
        $autoCreditStatus = 'neutral';
        if (count($allLoans) > 0) {
            $approvedCount = 0;
            $grantedCount = 0;
            $disapprovedCount = 0;
            
            foreach ($allLoans as $loan) {
                switch ($loan['status']) {
                    case 'approved':
                        $approvedCount++;
                        break;
                    case 'loan_granted':
                        $grantedCount++;
                        break;
                    case 'disapproved':
                        $disapprovedCount++;
                        break;
                }
            }
            
            $autoCreditScore = 50;
            $autoCreditScore += ($approvedCount * 10);
            $autoCreditScore += ($grantedCount * 20);
            $autoCreditScore -= ($disapprovedCount * 15);
            $autoCreditScore = max(0, min(100, $autoCreditScore));
            
            $autoCreditStatus = $autoCreditScore >= 70 ? 'good' : 'bad';
        }
        
        $creditStatus = $creditScoreOverride ? $creditScoreOverride : $autoCreditStatus;
        
        // Block BAD payers
        if ($creditStatus === 'bad') {
            header('Location: ../home.php?error=bad_payer');
            exit;
        }
        
        // Check if user has existing e-loan and redirect to application status
        $stmt = $pdo->prepare("
            SELECT id, status
            FROM e_loans
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        $existingLoan = $stmt->fetch();
        
        if ($existingLoan) {
            // Redirect to application status page
            header('Location: application_status.php?loan_id=' . $existingLoan['id'] . '&loan_type=e_loan');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E Loan Application - Loan System</title>
    <!-- Google Maps API - Replace YOUR_API_KEY with your actual Google Maps API key -->
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            color: #210a1a;
            font-size: 24px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 10, 26, 0.4);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .loan-info {
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .loan-info h2 {
            font-size: 28px;
            margin-bottom: 15px;
        }

        .loan-info p {
            font-size: 16px;
            line-height: 1.8;
        }

        .requirements-section {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .requirements-section h3 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .requirements-section ul {
            list-style: none;
            padding-left: 0;
        }

        .requirements-section li {
            padding: 8px 0;
            color: #856404;
            font-size: 15px;
        }

        .requirements-section li:before {
            content: "📄 ";
            margin-right: 8px;
        }

        .signature-note {
            background: #e7f3ff;
            border-left: 4px solid #210a1a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .signature-note p {
            color: #333;
            font-size: 14px;
            margin: 0;
        }

        .signature-note strong {
            color: #210a1a;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group label.required:after {
            content: " *";
            color: #dc3545;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group input[type="tel"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #210a1a;
            box-shadow: 0 0 0 3px rgba(33, 10, 26, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .file-upload-area {
            border: 2px dashed #210a1a;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-area:hover {
            background: #e9ecef;
            border-color: #41081a;
        }

        .file-upload-area.dragover {
            background: #e7f3ff;
            border-color: #210a1a;
        }

        .file-upload-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .file-upload-text {
            color: #666;
            font-size: 14px;
        }

        .file-upload-input {
            display: none;
        }

        .preview-container {
            margin-top: 15px;
            display: none;
        }

        .preview-container.show {
            display: block;
        }

        .preview-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            margin-top: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .camera-section {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .camera-preview {
            width: 100%;
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 15px;
            display: none;
        }

        .camera-preview.show {
            display: block;
        }

        .camera-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-camera {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-camera-primary {
            background: #28a745;
            color: white;
        }

        .btn-camera-primary:hover {
            background: #218838;
        }

        .btn-camera-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-camera-secondary:hover {
            background: #5a6268;
        }

        .btn-camera-danger {
            background: #dc3545;
            color: white;
        }

        .btn-camera-danger:hover {
            background: #c82333;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(33, 10, 26, 0.4);
            margin-top: 20px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 10, 26, 0.5);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .success-message {
            background: #efe;
            color: #3c3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .success-message.show {
            display: block;
        }

        .review-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-out;
        }

        .review-modal.active {
            display: flex;
        }

        .review-modal-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            text-align: center;
            position: relative;
        }

        .review-modal-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .review-modal-content h2 {
            color: #210a1a;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .review-modal-content p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .review-modal-close {
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .review-modal-close:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 10, 26, 0.4);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .card {
                padding: 25px 20px;
            }

            .header {
                flex-direction: column;
                text-align: center;
            }

            .camera-controls {
                flex-direction: column;
            }

            .btn-camera {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💻 E Loan Application</h1>
            <a href="../home.php" class="btn btn-secondary">← Back to Home</a>
        </div>

        <div class="card">
            <div class="loan-info">
                <h2>E Loan</h2>
                <p>10 days only , if you exceed more than 10days you need to pay another 20% of the whole amount that you need to pay. That is payable within 5days if it exceed more than 5days the loan amount to pay will increase again so bare in mind that you have to settle your eloan on time to prevent this in the future.  “Sample computation” (Principal amount x 20% = additional interest) (additional interest + principal amount = the total to be paid within 5 days of exceeding the due date.</p>
            </div>

            <div class="requirements-section">
                <h3>📋 Requirements Needed to be Submitted:</h3>
                <ul>
                    <li><strong>2 Valid IDs with 3 signatures written on a white paper</strong></li>
                    <li style="padding-left: 20px;">- Company ID</li>
                    <li style="padding-left: 20px;">- Government ID</li>
                </ul>
            </div>

            <div class="signature-note">
                <p><strong>⚠️ Important:</strong> Both IDs must be placed on a white paper with <strong>3 signatures</strong> written on the same paper. Please ensure all signatures are clearly visible.</p>
            </div>

            <div class="error-message" id="errorMessage"></div>
            <div class="success-message" id="successMessage"></div>

            <form id="eLoanForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="loanAmount" class="required">Loan Amount</label>
                    <input type="number" id="loanAmount" name="loanAmount" min="1000" step="1000" placeholder="Enter loan amount" required>
                </div>

                <div class="form-group">
                    <label for="companyIdType" class="required">Company ID Type</label>
                    <select id="companyIdType" name="companyIdType" required>
                        <option value="">Select Company ID Type</option>
                        <option value="Employee ID">Employee ID</option>
                        <option value="Company ID Card">Company ID Card</option>
                        <option value="Work ID">Work ID</option>
                        <option value="Office ID">Office ID</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="governmentIdType" class="required">Government ID Type</label>
                    <select id="governmentIdType" name="governmentIdType" required>
                        <option value="">Select Government ID Type</option>
                        <option value="SSS ID">SSS ID</option>
                        <option value="PhilHealth ID">PhilHealth ID</option>
                        <option value="TIN ID">TIN ID</option>
                        <option value="Driver's License">Driver's License</option>
                        <option value="Passport">Passport</option>
                        <option value="National ID">National ID</option>
                        <option value="Postal ID">Postal ID</option>
                        <option value="Voter's ID">Voter's ID</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="idsWithSignatures" class="required">Upload 2 Valid IDs with 3 Signatures on White Paper</label>
                    <div class="file-upload-area" onclick="document.getElementById('idsWithSignatures').click()">
                        <div class="file-upload-icon">📄</div>
                        <div class="file-upload-text">Click to upload photo of IDs with 3 signatures on white paper</div>
                        <div class="file-upload-text" style="font-size: 12px; margin-top: 5px; color: #999;">
                            Must include: Company ID + Government ID + 3 Signatures on white paper
                        </div>
                        <input type="file" id="idsWithSignatures" name="idsWithSignatures" accept="image/*" class="file-upload-input" required>
                    </div>
                    <div class="preview-container" id="idsPreview">
                        <img id="idsPreviewImg" class="preview-image" alt="IDs with Signatures Preview">
                    </div>
                </div>

                <div class="form-group">
                    <label>Take Picture for Verification</label>
                    <div class="camera-section">
                        <video id="video" class="camera-preview" autoplay></video>
                        <canvas id="canvas" style="display: none;"></canvas>
                        <div class="camera-controls">
                            <button type="button" class="btn-camera btn-camera-primary" id="startCamera">📷 Start Camera</button>
                            <button type="button" class="btn-camera btn-camera-secondary" id="capturePhoto" style="display: none;">📸 Capture Photo</button>
                            <button type="button" class="btn-camera btn-camera-danger" id="stopCamera" style="display: none;">⏹ Stop Camera</button>
                        </div>
                        <div class="preview-container" id="verificationPhotoPreview" style="display: none;">
                            <img id="verificationPhotoImg" class="preview-image" alt="Verification Photo">
                            <input type="hidden" id="verificationPhoto" name="verificationPhoto">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>E Loan Agreement (Handwritten)</label>
                    <button type="button" class="agreement-btn" onclick="openAgreementModal()">
                        📄 View E Loan Agreement
                    </button>
                </div>

                <div class="form-group">
                    <label for="contactNumber" class="required">Contact Number</label>
                    <input type="tel" id="contactNumber" name="contactNumber" placeholder="09XX XXX XXXX" required>
                </div>

                <div class="form-group">
                    <label for="address">Address (Optional)</label>
                    <textarea id="address" name="address" placeholder="Enter your address" onblur="geocodeAddress()"></textarea>
                    <div id="mapContainer" style="display: none; margin-top: 15px;">
                        <div id="map" style="width: 100%; height: 300px; border-radius: 8px; border: 2px solid #e0e0e0;"></div>
                        <p style="margin-top: 10px; font-size: 12px; color: #666; text-align: center;">📍 Location pinned on map</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="loanPurpose">Purpose of Loan (Optional)</label>
                    <textarea id="loanPurpose" name="loanPurpose" placeholder="Briefly describe the purpose of this loan"></textarea>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">Submit E Loan Application</button>
            </form>
        </div>
    </div>

    <!-- E Loan Agreement Modal -->
    <div id="agreementModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeAgreementModal()">&times;</span>
            <img src="../img/contract.png" alt="E Loan Agreement" class="modal-image" onerror="this.alt='Contract image not found'">
        </div>
    </div>

    <!-- Review Notification Modal -->
    <div id="reviewModal" class="review-modal">
        <div class="review-modal-content">
            <div class="review-modal-icon">📋</div>
            <h2>Application Submitted</h2>
            <p>We would like to inform you that your submitted documents are currently under review. Our team will complete the review within 24 hours.</p>
            <button class="review-modal-close" onclick="closeReviewModal()">Got it</button>
        </div>
    </div>

    <script>
        let stream = null;
        let capturedPhoto = null;

        // File upload preview
        document.getElementById('idsWithSignatures').addEventListener('change', function(e) {
            previewImage(e.target, 'idsPreview', 'idsPreviewImg');
        });


        function previewImage(input, containerId, imgId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(imgId).src = e.target.result;
                    document.getElementById(containerId).classList.add('show');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Google Maps variables
        let map;
        let marker;
        let geocoder;

        // Initialize map
        function initMap() {
            geocoder = new google.maps.Geocoder();
            const defaultLocation = { lat: 14.5995, lng: 120.9842 }; // Default to Manila, Philippines
            
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 15,
                center: defaultLocation,
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: true
            });

            marker = new google.maps.Marker({
                map: map,
                draggable: false
            });
        }

        // Geocode address and show on map
        function geocodeAddress() {
            const address = document.getElementById('address').value.trim();
            const mapContainer = document.getElementById('mapContainer');
            
            if (!address) {
                mapContainer.style.display = 'none';
                return;
            }

            if (!geocoder) {
                initMap();
            }

            geocoder.geocode({ address: address }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    const location = results[0].geometry.location;
                    
                    // Center map on location
                    map.setCenter(location);
                    map.setZoom(15);
                    
                    // Set marker position
                    marker.setPosition(location);
                    marker.setMap(map);
                    
                    // Show map container
                    mapContainer.style.display = 'block';
                } else {
                    // If geocoding fails, hide map
                    mapContainer.style.display = 'none';
                    console.error('Geocode was not successful for the following reason: ' + status);
                }
            });
        }

        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map with default location
            if (typeof google !== 'undefined' && google.maps) {
                initMap();
            }
        });

        // Agreement modal functions
        function openAgreementModal() {
            document.getElementById('agreementModal').style.display = 'flex';
        }

        function closeAgreementModal() {
            document.getElementById('agreementModal').style.display = 'none';
        }

        // Close modal when clicking outside the image
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('agreementModal');
            if (event.target === modal) {
                closeAgreementModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAgreementModal();
            }
        });

        // Camera functionality
        document.getElementById('startCamera').addEventListener('click', async function() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'user' } 
                });
                const video = document.getElementById('video');
                video.srcObject = stream;
                video.classList.add('show');
                document.getElementById('startCamera').style.display = 'none';
                document.getElementById('capturePhoto').style.display = 'inline-block';
                document.getElementById('stopCamera').style.display = 'inline-block';
            } catch (err) {
                alert('Error accessing camera: ' + err.message);
            }
        });

        document.getElementById('capturePhoto').addEventListener('click', function() {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const context = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0);
            
            capturedPhoto = canvas.toDataURL('image/png');
            document.getElementById('verificationPhotoImg').src = capturedPhoto;
            document.getElementById('verificationPhoto').value = capturedPhoto;
            document.getElementById('verificationPhotoPreview').style.display = 'block';
        });

        document.getElementById('stopCamera').addEventListener('click', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            document.getElementById('video').classList.remove('show');
            document.getElementById('startCamera').style.display = 'inline-block';
            document.getElementById('capturePhoto').style.display = 'none';
            document.getElementById('stopCamera').style.display = 'none';
        });

        // Form submission
        document.getElementById('eLoanForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;

            // Create FormData
            const formData = new FormData(this);
            
            // Add verification photo if captured
            if (capturedPhoto) {
                const blob = dataURLtoBlob(capturedPhoto);
                formData.append('verificationPhotoFile', blob, 'verification-photo.png');
            }


            // Submit form
            fetch('api/submit_e_loan.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to application status page
                    window.location.href = `application_status.php?loan_id=${data.loan_id}&loan_type=e_loan`;
                } else {
                    showError(data.message || 'Failed to submit application. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit E Loan Application';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Network error. Please check your connection and try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit E Loan Application';
            });
        });

        function dataURLtoBlob(dataurl) {
            const arr = dataurl.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while(n--) {
                u8arr[n] = bstr.charCodeAt(n);
            }
            return new Blob([u8arr], {type:mime});
        }

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.classList.add('show');
            setTimeout(() => {
                errorDiv.classList.remove('show');
            }, 5000);
        }

        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.classList.add('show');
            setTimeout(() => {
                successDiv.classList.remove('show');
            }, 5000);
        }

        function showReviewModal() {
            document.getElementById('reviewModal').classList.add('active');
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('reviewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReviewModal();
            }
        });
    </script>
    <style>
        .agreement-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(33, 10, 26, 0.3);
        }

        .agreement-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 10, 26, 0.4);
        }

        .agreement-btn:active {
            transform: translateY(0);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }

        .modal-image {
            max-width: 100%;
            max-height: 80vh;
            width: auto;
            height: auto;
            border-radius: 8px;
            display: block;
        }

        .modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            color: #fff;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            background: rgba(0, 0, 0, 0.5);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(0, 0, 0, 0.8);
            transform: rotate(90deg);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        #mapContainer {
            margin-top: 15px;
        }

        #map {
            width: 100%;
            height: 300px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .modal-content {
                max-width: 95%;
                padding: 15px;
            }

            .modal-close {
                top: 5px;
                right: 10px;
                width: 35px;
                height: 35px;
                font-size: 28px;
            }

            #map {
                height: 250px;
            }
        }
    </style>
</body>
</html>
