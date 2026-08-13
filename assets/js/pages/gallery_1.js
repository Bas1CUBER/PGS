        document.addEventListener("DOMContentLoaded", function() {
            var photoGrid = document.querySelector(".photo-grid");
            if (photoGrid) {
                photoGrid.addEventListener("click", function(e) {
                    var photoImg = e.target.closest(".photo-img");
                    if (photoImg && !e.target.closest(".photo-actions")) {
                        var filename = photoImg.getAttribute("data-filename");
                        var caption = photoImg.getAttribute("data-caption");
                        if (filename) {
                            var zoomImg = document.getElementById("zoomImg");
                            var captionEl = document.getElementById("zoomCaption");
                            zoomImg.src = "gallery_uploads/" + filename;
                            if (caption && caption.trim() !== "") {
                                captionEl.textContent = caption;
                                captionEl.style.display = "block";
                            } else {
                                captionEl.style.display = "none";
                            }
                            var zoomModal = new bootstrap.Modal(document.getElementById("zoomModal"));
                            zoomModal.show();
                        }
                    }
                    var editBtn = e.target.closest(".edit-caption-btn");
                    if (editBtn) {
                        e.stopPropagation();
                        var photoId = editBtn.getAttribute("data-photo-id");
                        var caption = editBtn.getAttribute("data-caption");
                        document.getElementById("captionPhotoId").value = photoId;
                        document.getElementById("captionText").value = caption || "";
                        var captionModal = new bootstrap.Modal(document.getElementById("captionModal"));
                        captionModal.show();
                    }
                    var deleteBtn = e.target.closest(".delete-photo-btn");
                    if (deleteBtn) {
                        e.stopPropagation();
                        var photoId = deleteBtn.getAttribute("data-photo-id");
                        if (confirm("Are you sure you want to delete this photo?")) {
                            document.getElementById("deletePhotoId").value = photoId;
                            document.getElementById("deletePhotoForm").submit();
                        }
                    }
            });
            }
            window.deleteAlbum = function(albumId) {
                if (confirm("Are you sure you want to delete this album and all its photos?")) {
                    document.getElementById("deleteAlbumId").value = albumId;
                    document.getElementById("deleteAlbumForm").submit();
                }
            };
            document.querySelectorAll(".edit-album-btn").forEach(function(btn) {
                btn.addEventListener("click", function(e) {
                    e.stopPropagation();
                    var albumId = this.getAttribute("data-album-id");
                    var albumName = this.getAttribute("data-album-name") || "";
                    var albumDescription = this.getAttribute("data-album-description") || "";
                    document.getElementById("editAlbumId").value = albumId;
                    document.getElementById("editAlbumName").value = albumName;
                    document.getElementById("editAlbumDescription").value = albumDescription;
                    var editAlbumModal = new bootstrap.Modal(document.getElementById("editAlbumModal"));
                    editAlbumModal.show();
                });
            });

            var photoInput = document.getElementById("photoInput");
            if (photoInput) {
                photoInput.addEventListener("change", function() {
                    var previewArea = document.getElementById("previewArea");
                    var previewGrid = document.getElementById("previewGrid");
                    previewGrid.innerHTML = "";
                    
                    if (this.files.length > 0) {
                        previewArea.style.display = "block";
                        
                        for (var i = 0; i < this.files.length; i++) {
                            var file = this.files[i];
                            if (file.type.startsWith("image/")) {
                                (function(index) {
                                    var reader = new FileReader();
                                    reader.onload = function(e) {
                                        var div = document.createElement("div");
                                        div.className = "preview-item";
                                        div.innerHTML = "<img src=\"" + e.target.result + "\" alt=\"Preview\">" +
                                            "<textarea name=\"captions[]\" placeholder=\"Caption (optional)\"></textarea>";
                                        previewGrid.appendChild(div);
                                    };
                                    reader.readAsDataURL(file);
                                })(i);
                            }
                        }
                    } else {
                        previewArea.style.display = "none";
                    }
                });
            }
        });
