console.log('POST JS LOADED');

(function (Drupal, once) {
    function renderPost(post) {
        return `
          <div class="post-card" data-nid="${post.id}">
      
            <div class="post-header">
              <div class="avatar">
                <img src="${post.avatar || '/themes/custom/product_theme/images/default-avatar.png'}">
              </div>
      
              <div class="user-info">
                <strong>${post.author}</strong><br>
                <small>${post.created}</small>
              </div>
      
              ${
                post.is_owner ? `
                <div class="post-actions">
                  <button class="edit-btn">✏️</button>
                  <button class="delete-btn">🗑️</button>
                </div>
                ` : ''
              }
            </div>
      
            <div class="post-content">
              <div class="post-body">${post.body}</div>
      
              ${post.image ? `
                <div class="post-image">
                  <img src="${post.image}">
                </div>
              ` : ''}
            </div>
      
            <div class="edit-area" style="display:none;">
              <textarea class="edit-body">${post.body}</textarea>
      
              <div class="upload-box">
                <label class="upload-label">
                  📷 Change Photo
                  <input type="file" class="edit-image" hidden>
                </label>
      
                <div class="upload-preview edit-preview">
                  ${post.image ? `<img src="${post.image}">` : ''}
                </div>
              </div>
      
              <div class="edit-actions">
                <button class="save-btn">Save</button>
                <button class="cancel-btn">Cancel</button>
              </div>
            </div>
      
            <div class="post-footer">
              <span>👍 Like</span>
              <span>💬 Comment</span>
              <span>🔁 Share</span>
            </div>
      
          </div>
        `;
    }

  Drupal.behaviors.postEdit = {
    attach: function (context) {
      /* =========================
         CREATE POST
      ========================= */
        once('createPost', context.querySelectorAll('.create-btn')).forEach(btn => {
            btn.addEventListener('click', function () {

            const box = btn.closest('.create-post-box');
            const body = box.querySelector('.create-body').value;
            const fileInput = box.querySelector('.create-image');

            let formData = new FormData();
            formData.append('body', body);

            if (fileInput && fileInput.files.length > 0) {
                formData.append('image', fileInput.files[0]);
            }

            if (!body.trim()) {
                alert('Post cannot be empty');
                return;
            }


            fetch('/custom/post/create', {
                method: 'POST',
                body: formData
            })
            .then(res => {
              if (!res.ok) throw new Error('Post failed');
              return res.json();
            })
            .then(data => {
            
              const post = data.data || data;
            
              // clear form
              box.querySelector('.create-body').value = '';
              box.querySelector('.create-image').value = '';
              box.querySelector('.upload-preview').innerHTML = '';
            
              const feed = document.getElementById('feed');
            
              const empty = document.querySelector('.empty-feed');
              if (empty) empty.style.display = 'none';
            
              if (feed && post) {
                const exists = feed.querySelector(`[data-nid="${post.id}"]`);
                if (!exists) {
                  feed.insertAdjacentHTML('afterbegin', renderPost(post));
                  Drupal.attachBehaviors(feed);
                }
              }
            
            })
            .catch(err => {
                console.error(err);
                alert('Post failed. Make sure content is not empty.');
            });
        });
    });


      /* =========================
         EDIT
      ========================= */
      once('editPost', context.querySelectorAll('.edit-btn')).forEach(btn => {
        btn.addEventListener('click', function () {

          const card = btn.closest('.post-card');

          card.querySelector('.post-content').style.display = 'none';
          card.querySelector('.edit-area').style.display = 'block';

          const preview = card.querySelector('.edit-preview');
          const original = card.querySelector('.post-image img');

          if (preview && original) {
            preview.innerHTML = `<img src="${original.src}">`;
          }

          const input = card.querySelector('.edit-image');
          if (input) input.value = '';
        });
      });


      /* =========================
         SAVE
      ========================= */
      once('savePost', context.querySelectorAll('.save-btn')).forEach(btn => {
        btn.addEventListener('click', function () {

          const card = btn.closest('.post-card');
          const nid = card.dataset.nid;
          const body = card.querySelector('.edit-body').value;
          const fileInput = card.querySelector('.edit-image');

          let formData = new FormData();
          formData.append('nid', nid);
          formData.append('body', body);

          if (fileInput && fileInput.files.length > 0) {
            formData.append('image', fileInput.files[0]);
          }

          fetch('/custom/post/update', {
            method: 'POST',
            body: formData
          })
          .then(res => res.json())
          .then(data => {

            const content = card.querySelector('.post-content');
            content.querySelector('.post-body').innerHTML = data.body;

            if (data.image) {
              let imgWrap = content.querySelector('.post-image');

              if (!imgWrap) {
                imgWrap = document.createElement('div');
                imgWrap.classList.add('post-image');
                content.appendChild(imgWrap);
              }

              imgWrap.innerHTML = `<img src="${data.image}">`;
            }

            card.querySelector('.edit-area').style.display = 'none';
            content.style.display = 'block';
          });

        });
      });


      /* =========================
         DELETE
      ========================= */
      once('deletePost', context.querySelectorAll('.delete-btn')).forEach(btn => {
        btn.addEventListener('click', function () {

          if (!confirm('Delete this post?')) return;

          const card = btn.closest('.post-card');
          const nid = card.dataset.nid;

          fetch('/custom/post/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'nid=' + nid
          }).then(() => {
            card.remove();
          });

        });
      });


      /* =========================
         CANCEL
      ========================= */
      once('cancelPost', context.querySelectorAll('.cancel-btn')).forEach(btn => {
        btn.addEventListener('click', function () {

          const card = btn.closest('.post-card');

          card.querySelector('.edit-area').style.display = 'none';
          card.querySelector('.post-content').style.display = 'block';

          const preview = card.querySelector('.edit-preview');
          const original = card.querySelector('.post-image img');

          if (preview && original) {
            preview.innerHTML = `<img src="${original.src}">`;
          }

          const input = card.querySelector('.edit-image');
          if (input) input.value = '';
        });
      });


      /* =========================
         CREATE IMAGE PREVIEW
      ========================= */
      once('createPreview', context.querySelectorAll('.create-image')).forEach(input => {
        input.addEventListener('change', function () {

          const box = input.closest('.upload-box');
          const preview = box.querySelector('.upload-preview');

          const file = input.files[0];
          if (!file) return;

          const reader = new FileReader();

          reader.onload = function (e) {
            preview.innerHTML = `
              <div class="preview-wrapper">
                <img src="${e.target.result}">
                <button class="remove-preview">✖</button>
              </div>
            `;
          };

          reader.readAsDataURL(file);
        });
      });


      /* =========================
         EDIT IMAGE PREVIEW (FINAL)
      ========================= */
      once('editPreviewFinal', context.querySelectorAll('.edit-image')).forEach(input => {
        input.addEventListener('change', function () {

          const card = input.closest('.post-card');
          const preview = card.querySelector('.edit-preview');

          const file = input.files[0];
          if (!file || !preview) return;

          const reader = new FileReader();

          reader.onload = function (e) {
            preview.innerHTML = `<img src="${e.target.result}">`;
          };

          reader.readAsDataURL(file);
        });
      });


      /* =========================
         REMOVE PREVIEW
      ========================= */
      once('removePreview', context.querySelectorAll('.upload-box')).forEach(box => {
        box.addEventListener('click', function (e) {
          if (e.target.classList.contains('remove-preview')) {
            box.querySelector('.upload-preview').innerHTML = '';
            box.querySelector('input[type="file"]').value = '';
          }
        });
      });


      /* =========================
         INFINITE SCROLL
      ========================= */
      once('infiniteScroll', document.body).forEach(() => {
        window.page = 1;
        window.loading = false;
        window.noMorePosts = false;
      
        let lastCall = 0;
      
        window.loadPosts = function () {
      
          if (window.loading || window.noMorePosts) return;
      
          const feed = document.getElementById('feed');
          if (!feed) return;
      
          const loadingEl = document.getElementById('loading');
      
          if (window.loading) return;
          window.loading = true;
          
          if (loadingEl) {
            loadingEl.style.display = 'block';
            loadingEl.innerText = 'Loading...';
          }
      
          fetch('/api/posts?page=' + window.page)
            .then(res => res.json())
            .then(posts => {
      
              if (!posts || posts.length === 0) {
                window.noMorePosts = true;
      
                if (loadingEl) {
                  loadingEl.innerText = 'No more posts';
                  setTimeout(() => loadingEl.style.display = 'none', 800);
                }
      
                window.loading = false;
                return;
              }
      
              posts.forEach(post => {
                const exists = feed.querySelector(`[data-nid="${post.id}"]`);
                if (exists) return;
      
                feed.insertAdjacentHTML('beforeend', renderPost(post));
              });
      
              Drupal.attachBehaviors(feed);
      
              window.page++;
              window.loading = false;
      
              if (loadingEl) loadingEl.style.display = 'none';
            })
            .catch(() => {
              window.loading = false;
            });
        };
      
        window.addEventListener('scroll', () => {
          const now = Date.now();
          if (now - lastCall < 500) return;
          lastCall = now;
      
          if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 200) {
            window.loadPosts();
          }
        });
      
  
        window.loadPosts();
      
      });

    }
  };
})(Drupal, once);