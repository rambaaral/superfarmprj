(function (wp) {
    'use strict';
    const el = wp.element.createElement, useState = wp.element.useState, useEffect = wp.element.useEffect;
    const { TextControl, TextareaControl, Button, Notice, Spinner } = wp.components;
    const hidden = 'style,script,noscript,template,svg,textarea,select,[hidden],[aria-hidden="true"]';
    // Template fragments never execute scripts. Text is changed with nodeValue, never interpreted as HTML.
    function model(html) {
        const root = document.createElement('template'); root.innerHTML = html;
        const walker = document.createTreeWalker(root.content, NodeFilter.SHOW_TEXT);
        const fields = []; let node;
        while ((node = walker.nextNode())) {
            const p = node.parentElement;
            if (!p || p.closest(hidden) || !node.nodeValue.trim() || /^\s*\[hpc\w+\]\s*$/.test(node.nodeValue)) continue;
            const tag = p.tagName.toLowerCase();
            const label = /^h[1-6]$/.test(tag) ? '제목' : p.closest('a') ? '링크 문구' : ['td','th'].includes(tag) ? '표 내용' : '문구';
            fields.push({ node, label, value: node.nodeValue, original:node.nodeValue, multiline:node.nodeValue.length>60, type: 'text' });
        }
        root.content.querySelectorAll('a[href]').forEach(a => {
            if (!a.closest(hidden)) fields.push({node:a,label:'링크 주소 · '+a.textContent.trim().slice(0,45),value:a.getAttribute('href'),type:'url'});
        });
        root.content.querySelectorAll('img').forEach(img => fields.push({node:img,label:'이미지 설명',value:img.getAttribute('alt') || '',type:'alt'}));
        return { root, fields };
    }
    function update(m, index, value) {
        const f = m.fields[index];
        if (f.type === 'text') f.node.nodeValue = value;
        else f.node.setAttribute(f.type === 'url' ? 'href' : 'alt', value);
        f.value = value;
        return m.root.innerHTML;
    }
    function Edit({attributes, setAttributes}) {
        const props = wp.blockEditor.useBlockProps({className:'hpcpc-editor'});
        const [filter, setFilter] = useState('');
        const source = attributes.html || '';
        const ref = wp.element.useRef(null);
        if (!ref.current || ref.current.html !== source) ref.current={html:source,model:model(source)};
        const m = ref.current.model;
        const texts = m.fields.filter(f=>f.type==='text');
        return el('div', props,
            el('h3',null,'센터 안내 내용'),
            el('p',null,'문구와 링크를 수정한 뒤 페이지 오른쪽 위의 저장을 누르세요. 디자인과 기능은 유지됩니다.'),
            el('p',null,(texts[0]?.value || '안내 영역').trim().slice(0,100)),
            el('details', null, el('summary',null,'문구·링크 편집 ('+m.fields.length+'개)'),
                el('div',{className:'hpcpc-fields'},
                    el(TextControl,{label:'수정할 문구 찾기',value:filter,onChange:setFilter}),
                    m.fields.map((f,i)=> {
                        if (filter && !((f.value+' '+(f.original||'')+' '+f.label).toLowerCase().includes(filter.toLowerCase()))) return null;
                        const Control = f.type === 'text' && f.multiline ? TextareaControl : TextControl;
                        return el(Control,{key:i,label:(i+1)+'. '+f.label,value:f.value,onChange:value=>{
                            if (f.type==='url' && /^\s*(javascript|data|vbscript):/i.test(value)) return;
                            const html=update(m,i,value);ref.current.html=html;setAttributes({html});
                        }});
                    })
                )
            )
        );
    }
    wp.blocks.registerBlockType('hpc/page-content', {
        apiVersion:3,title:'센터 안내 내용',category:'text',icon:'edit-page',
        attributes:{html:{type:'string',source:'html',default:''}},
        supports:{html:false,customClassName:false,inserter:false,reusable:false},
        edit:Edit,save:({attributes})=>el(wp.element.RawHTML,null,attributes.html),
        transforms:{to:[{type:'block',blocks:['core/html'],transform:attributes=>wp.blocks.createBlock('core/html',{content:attributes.html})}]}
    });
    function FaqItem({item}) {
        const [title,setTitle]=useState(item.title.raw),[body,setBody]=useState(item.content.raw);
        const [saved,setSaved]=useState({title:item.title.raw,body:item.content.raw,modified:item.modified_gmt});
        const [busy,setBusy]=useState(false),[status,setStatus]=useState(''),[error,setError]=useState(false);
        const dirty=title!==saved.title || body!==saved.body;
        useEffect(()=>{
            const warn=e=>{if(dirty){e.preventDefault();e.returnValue='';}};
            window.addEventListener('beforeunload',warn); return()=>window.removeEventListener('beforeunload',warn);
        },[dirty]);
        async function save() {
            setBusy(true);setStatus('');setError(false);
            try {
                const result=await wp.apiFetch({path:'/hpc-page-content/v1/faq/'+item.id,method:'POST',data:{title,content:body,expected_title:saved.title,expected_content:saved.body}});
                setSaved({title:result.title,body:result.content,modified:result.modified});setTitle(result.title);setBody(result.content);setStatus('FAQ 원본 저장 완료 · 사이트와 챗봇에 함께 반영됩니다.');
            } catch(e) {setError(true);setStatus(e.message || '저장하지 못했습니다.');}
            finally{setBusy(false);}
        }
        return el('details',{className:'hpcpc-faq'},el('summary',null,title),
            el(TextControl,{label:'질문',value:title,onChange:setTitle}),
            el(TextareaControl,{label:'답변',value:body,onChange:setBody,rows:7}),
            el(Button,{variant:'primary',disabled:busy || !dirty || !title.trim() || !body.trim(),onClick:save},busy?'저장 중…':'이 FAQ 저장'),
            dirty && el('p',null,'이 FAQ의 변경 사항은 아직 저장되지 않았습니다.'),
            status && el('p',{role:'status',className:error?'hpcpc-error':'hpcpc-status'},status)
        );
    }
    function Guide() {
        const content=wp.data.useSelect(select=>select('core/editor').getEditedPostContent(),[]);
        const [items,setItems]=useState(null),[error,setError]=useState('');
        const faq=content.includes('[hpc_support_faq]');
        useEffect(()=>{if(faq)wp.apiFetch({path:'/wp/v2/hkc_faq?context=edit&status=publish&per_page=100&orderby=id&order=asc'}).then(setItems).catch(e=>setError(e.message));},[faq]);
        const Panel=wp.editor.PluginDocumentSettingPanel;
        if(!Panel)return null;
        const links=[];
        if(content.includes('[hpc_notices]') || content.includes('[hpc_home_updates]'))links.push(['공지사항 내용','edit.php?post_type=hpc_notice']);
        if(content.includes('[hpc_calendar]'))links.push(['교육·행사 일정','edit.php?post_type=hpc_event']);
        if(content.includes('[hpc_home_updates]'))links.push(['배너 내용','edit.php?post_type=hpc_banner']);
        return el(Panel,{name:'hpc-page-content-guide',title:'센터 페이지 편집 안내',initialOpen:true},
            el('p',null,'본문의 「센터 안내 내용」에서 문구와 링크를 바꿀 수 있습니다. 저장 후 페이지 미리보기로 확인하세요.'),
            links.map(([label,href])=>el('p',{key:href},el('a',{href},label+' 관리 →'))),
            /\[hpc_portal_/.test(content) && el('p',null,'신청·로그인·포럼 영역은 사용자 상태에 따라 표시됩니다. 안내 문장은 기능 블록 위·아래에 문단 블록을 추가해 작성하세요.'),
            faq && el('div',null,el('h3',null,'연결된 공식 FAQ'),el('p',null,'아래 답변은 FAQ 원본입니다. 각 「이 FAQ 저장」을 눌러야 반영됩니다. 페이지 저장과 별도로 저장됩니다.'),
                !items && !error && el(Spinner),error && el(Notice,{status:'error',isDismissible:false},error),items && items.map(item=>el(FaqItem,{key:item.id,item})),
                el('p',null,el('a',{href:'edit.php?post_type=hkc_faq'},'FAQ 추가·삭제 및 전체 관리 →')))
        );
    }
    wp.plugins.registerPlugin('hpc-page-content-guide',{render:Guide});
})(window.wp);
